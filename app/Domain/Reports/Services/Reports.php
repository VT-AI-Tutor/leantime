<?php

namespace Leantime\Domain\Reports\Services;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Configuration\AppSettings as AppSettingCore;
use Leantime\Core\Configuration\Environment as EnvironmentCore;
use Leantime\Core\Events\DispatchesEvents;
use Leantime\Core\UI\Template as TemplateCore;
use Leantime\Domain\Blueprints\Repositories\Blueprints as BlueprintsRepository;
use Leantime\Domain\Clients\Repositories\Clients as ClientRepository;
use Leantime\Domain\Comments\Repositories\Comments as CommentRepository;
use Leantime\Domain\Ideas\Repositories\Ideas as IdeaRepository;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Leantime\Domain\Reactions\Repositories\Reactions;
use Leantime\Domain\Reports\Repositories\Reports as ReportRepository;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Setting\Services\Setting as SettingsService;
use Leantime\Domain\Sprints\Repositories\Sprints as SprintRepository;
use Leantime\Domain\Sprints\Services\Sprints as SprintService;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Domain\Timesheets\Repositories\Timesheets as TimesheetRepository;
use Leantime\Domain\Users\Repositories\Users as UserRepository;

/**
 * @api
 */
class Reports
{
    use DispatchesEvents;

    private TemplateCore $tpl;

    private AppSettingCore $appSettings;

    private EnvironmentCore $config;

    private ProjectRepository $projectRepository;

    private SprintRepository $sprintRepository;

    private ReportRepository $reportRepository;

    private SettingsService $settings;

    private TicketRepository $ticketRepository;

    private SprintService $sprintService;

    /**
     * @param  SettingRepository  $settings
     */
    public function __construct(
        TemplateCore $tpl,
        AppSettingCore $appSettings,
        EnvironmentCore $config,
        ProjectRepository $projectRepository,
        SprintRepository $sprintRepository,
        ReportRepository $reportRepository,
        SettingsService $settings,
        TicketRepository $ticketRepository,
        SprintService $sprintService
    ) {
        $this->tpl = $tpl;
        $this->appSettings = $appSettings;
        $this->config = $config;
        $this->projectRepository = $projectRepository;
        $this->sprintRepository = $sprintRepository;
        $this->reportRepository = $reportRepository;
        $this->settings = $settings;
        $this->ticketRepository = $ticketRepository;
        $this->sprintService = $sprintService;
    }

    /**
     * Resolves which sprint burndown to display on the reports page.
     *
     * Mirrors the legacy controller selection order exactly:
     * 1. An explicitly requested sprint id (from the query string).
     * 2. Otherwise the project's current sprint.
     * 3. Otherwise the first available sprint.
     *
     * The returned 'currentSprintId' preserves the original behaviour:
     * when a sprint id is explicitly requested it is echoed back as-is
     * (even if the sprint cannot be loaded); when falling back to the
     * current/first sprint the resolved sprint object's id is used.
     * When the project has no sprints at all, both values are false.
     *
     * @param  int  $projectId  Project to resolve the burndown for.
     * @param  int|null  $requestedSprintId  Sprint id explicitly requested by the user, or null.
     * @return array{chart: false|array, currentSprintId: int|false} Burndown chart data and the resolved sprint id.
     *
     * @api
     */
    public function getSprintBurndownForReport(int $projectId, ?int $requestedSprintId): array
    {
        $allSprints = $this->sprintService->getAllSprints($projectId);

        if ($allSprints === false || count($allSprints) === 0) {
            return ['chart' => false, 'currentSprintId' => false];
        }

        $sprintChart = false;

        if ($requestedSprintId !== null) {
            $sprintObject = $this->sprintService->getSprint($requestedSprintId);
            if ($sprintObject) {
                $sprintChart = $this->sprintService->getSprintBurndown($sprintObject);
            }

            return ['chart' => $sprintChart, 'currentSprintId' => $requestedSprintId];
        }

        $currentSprint = $this->sprintService->getCurrentSprintId($projectId);

        if ($currentSprint !== false && $currentSprint !== 'all') {
            $sprintObject = $this->sprintService->getSprint((int) $currentSprint);
            if ($sprintObject) {
                $sprintChart = $this->sprintService->getSprintBurndown($sprintObject);

                return ['chart' => $sprintChart, 'currentSprintId' => $sprintObject->id];
            }

            return ['chart' => $sprintChart, 'currentSprintId' => false];
        }

        $sprintChart = $this->sprintService->getSprintBurndown($allSprints[0]);

        return ['chart' => $sprintChart, 'currentSprintId' => $allSprints[0]->id];
    }

    /**
     * @throws BindingResolutionException
     *
     * @api
     */
    public function dailyIngestion(): void
    {
        $this->runIngestionForProject(session('currentProject'));
    }

    protected function runIngestionForProject(int $projectId): void
    {

        if (Cache::has('dailyReports-'.$projectId) === false || Cache::get('dailyReports-'.$projectId) < dtHelper()->dbNow()->endOfDay()) {

            // Check if the dailyingestion cycle was executed already. There should be one entry for backlog and one entry for current sprint (unless there is no current sprint
            // Get current Sprint Id, if no sprint available, dont run the sprint burndown

            $lastEntries = $this->reportRepository->checkLastReportEntries($projectId);

            // If we receive 2 entries we have a report already. If we have one entry then we ran the backlog one and that means there was no current sprint.
            if (count($lastEntries) == 0) {
                $currentSprint = $this->sprintRepository->getCurrentSprint($projectId);

                if ($currentSprint !== false) {
                    $sprintReport = $this->reportRepository->runTicketReport($projectId, $currentSprint->id);
                    if ($sprintReport !== false) {
                        $this->reportRepository->addReport($sprintReport);
                    }
                }

                $backlogReport = $this->reportRepository->runTicketReport($projectId, '');

                if ($backlogReport !== false) {

                    $this->reportRepository->addReport($backlogReport);

                    Cache::put('dailyReports-'.$projectId, dtHelper()->dbNow()->endOfDay(), 14400); // 4hours

                }
            }

        }
    }

    public function cronDailyIngestion(): void
    {
        $projects = $this->projectRepository->getAll();

        foreach ($projects as $project) {
            $this->runIngestionForProject($project['id']);
        }

    }

    /**
     * @api
     */
    public function getFullReport($projectId): false|array
    {
        return $this->reportRepository->getFullReport($projectId);
    }

    /**
     * @throws BindingResolutionException
     *
     * @api
     */
    public function getRealtimeReport($projectId, $sprintId): array|bool
    {
        return $this->reportRepository->runTicketReport($projectId, $sprintId);
    }

    /**
     * Builds a per-team-member performance report for a project and a single month.
     *
     * Combines the project's assigned members with timesheet entries (hours and
     * distinct tasks worked, bucketed per timezone-local day) and tasks completed
     * in the month. Members with no activity are still included so the manager
     * sees the full team. The result feeds both a team-comparison chart and a
     * per-member daily drill-down on the reports page.
     *
     * @param  int  $projectId  Project to report on.
     * @param  string  $startUtc  Inclusive month start (UTC, 'Y-m-d H:i:s').
     * @param  string  $endUtc  Exclusive month end (UTC, 'Y-m-d H:i:s').
     * @param  string  $timezone  User timezone used to bucket entries into local days.
     * @param  int  $daysInMonth  Number of days in the selected month.
     * @return array{members: array<int, array<string, mixed>>, teamDaily: array<int, float>, days: int}
     *
     * @throws BindingResolutionException
     *
     * @api
     */
    public function getTeamPerformanceReport(int $projectId, string $startUtc, string $endUtc, string $timezone, int $daysInMonth): array
    {
        $members = $this->projectRepository->getUsersAssignedToProject($projectId);
        $members = is_array($members) ? $members : [];

        $rows = [];
        $workedTickets = [];

        // Seed a row for every assigned member so 0-activity members still show.
        foreach ($members as $member) {
            $uid = (int) $member['id'];
            $rows[$uid] = $this->emptyTeamRow(
                $uid,
                $this->memberName($member['firstname'] ?? '', $member['lastname'] ?? '', $member['username'] ?? '', $uid),
                $daysInMonth
            );
            $workedTickets[$uid] = [];
        }

        // Timesheet entries -> hours, distinct tasks worked and per-local-day buckets.
        foreach ($this->reportRepository->getTimesheetEntriesForMonth($projectId, $startUtc, $endUtc) as $entry) {
            $uid = (int) ($entry['userId'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            if (! isset($rows[$uid])) {
                $rows[$uid] = $this->emptyTeamRow(
                    $uid,
                    $this->memberName($entry['firstname'] ?? '', $entry['lastname'] ?? '', $entry['username'] ?? '', $uid),
                    $daysInMonth
                );
                $workedTickets[$uid] = [];
            }

            $hours = (float) ($entry['hours'] ?? 0);
            $rows[$uid]['hours'] += $hours;

            if (! empty($entry['ticketId'])) {
                $workedTickets[$uid][(int) $entry['ticketId']] = true;
            }

            if (! empty($entry['workDate'])) {
                $localDay = (int) CarbonImmutable::parse($entry['workDate'], 'UTC')->setTimezone($timezone)->day;
                if ($localDay >= 1 && $localDay <= $daysInMonth) {
                    $rows[$uid]['daily'][$localDay - 1] += $hours;
                }
            }
        }

        // Tasks completed in the month, attributed to the assignee.
        foreach ($this->reportRepository->getDoneTaskStatsForMonth($projectId, $startUtc, $endUtc) as $stat) {
            $uid = (int) ($stat['userId'] ?? 0);
            if ($uid <= 0 || ! isset($rows[$uid])) {
                continue;
            }
            $rows[$uid]['tasksDone'] += (int) $stat['done_count'];
            $rows[$uid]['pointsDone'] += (float) $stat['points_done'];
        }

        foreach ($rows as $uid => &$row) {
            $row['tasksWorked'] = count($workedTickets[$uid] ?? []);
            $row['hours'] = round($row['hours'], 2);
            $row['daily'] = array_map(fn ($h) => round($h, 2), $row['daily']);
        }
        unset($row);

        $result = array_values($rows);
        usort($result, fn ($a, $b) => $b['hours'] <=> $a['hours']);

        // Whole-team total per local day for the team drill-down chart.
        $teamDaily = array_fill(0, $daysInMonth, 0.0);
        foreach ($result as $row) {
            foreach ($row['daily'] as $i => $h) {
                $teamDaily[$i] += $h;
            }
        }
        $teamDaily = array_map(fn ($h) => round($h, 2), $teamDaily);

        return [
            'members' => $result,
            'teamDaily' => $teamDaily,
            'days' => $daysInMonth,
        ];
    }

    /**
     * Returns a zeroed monthly team-performance row for a member.
     *
     * @param  int  $id  User id.
     * @param  string  $name  Display name.
     * @param  int  $daysInMonth  Number of day buckets to pre-fill with 0.
     * @return array<string, mixed>
     */
    private function emptyTeamRow(int $id, string $name, int $daysInMonth): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'hours' => 0.0,
            'tasksDone' => 0,
            'tasksWorked' => 0,
            'pointsDone' => 0.0,
            'daily' => array_fill(0, $daysInMonth, 0.0),
        ];
    }

    /**
     * Resolves a human-readable member name with sensible fallbacks.
     *
     * @param  string  $firstname  First name (may be empty).
     * @param  string  $lastname  Last name (may be empty).
     * @param  string  $username  Username fallback (may be empty).
     * @param  int  $id  User id, used as a last-resort label.
     */
    private function memberName(string $firstname, string $lastname, string $username, int $id): string
    {
        $name = trim($firstname.' '.$lastname);

        if ($name !== '') {
            return $name;
        }

        return $username !== '' ? $username : 'User '.$id;
    }

    /**
     * @api
     */
    public function getAnonymousTelemetry(
        IdeaRepository $ideaRepository,
        UserRepository $userRepository,
        ClientRepository $clientRepository,
        CommentRepository $commentsRepository,
        TimesheetRepository $timesheetRepo,
        BlueprintsRepository $blueprintsRepo
    ): array {

        // Get anonymous company guid
        $companyId = $this->settings->getCompanyId();

        self::dispatch_event('beforeTelemetrySend', ['companyId' => $companyId]);

        $companyLang = $this->settings->getSetting('companysettings.language');
        if ($companyLang != '' && $companyLang !== false) {
            $currentLanguage = $companyLang;
        } else {
            $currentLanguage = $this->config->language;
        }

        $projectStatusCount = $this->getProjectStatusReport();

        $taskSentiment = $this->generateTicketReactionsReport();

        $telemetry = [
            'date' => '',
            'companyId' => $companyId,
            'env' => 'oss',
            'version' => $this->appSettings->appVersion,
            'language' => $currentLanguage,
            'numUsers' => $userRepository->getNumberOfUsers(),
            'lastUserLogin' => $userRepository->getLastLogin(),

            'numProjects' => $this->projectRepository->getNumberOfProjects(null, 'project'),
            'numProjectsGreen' => $projectStatusCount['green'] ?? 0,
            'numProjectsYellow' => $projectStatusCount['yellow'] ?? 0,
            'numProjectsRed' => $projectStatusCount['red'] ?? 0,
            'numProjectsNone' => $projectStatusCount['none'] ?? 0,

            'numStrategies' => $this->projectRepository->getNumberOfProjects(null, 'strategy'),
            'numPrograms' => $this->projectRepository->getNumberOfProjects(null, 'program'),
            'numClients' => $clientRepository->getNumberOfClients(),
            'numComments' => $commentsRepository->countComments(),
            'numMilestones' => $this->ticketRepository->getNumberOfMilestones(),
            'numTickets' => $this->ticketRepository->getNumberOfAllTickets(),

            'numBoards' => $ideaRepository->getNumberOfBoards(),

            'numIdeaItems' => $ideaRepository->getNumberOfIdeas(),
            'numHoursBooked' => $timesheetRepo->getHoursBooked(),

            'numResearchBoards' => $blueprintsRepo->getNumberOfBoards(null, 'leancanvas'),
            'numResearchItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'leancanvas'),

            'numRetroBoards' => $blueprintsRepo->getNumberOfBoards(null, 'retroscanvas'),
            'numRetroItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'retroscanvas'),

            'numGoalBoards' => $blueprintsRepo->getNumberOfBoards(null, 'goalcanvas'),
            'numGoalItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'goalcanvas'),

            'numValueCanvasBoards' => $blueprintsRepo->getNumberOfBoards(null, 'valuecanvas'),
            'numValueCanvasItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'valuecanvas'),

            'numMinEmpathyBoards' => $blueprintsRepo->getNumberOfBoards(null, 'minempathycanvas'),
            'numMinEmpathyItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'minempathycanvas'),

            'numOBMBoards' => $blueprintsRepo->getNumberOfBoards(null, 'obmcanvas'),
            'numOBMItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'obmcanvas'),

            'numSWOTBoards' => $blueprintsRepo->getNumberOfBoards(null, 'swotcanvas'),
            'numSWOTItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'swotcanvas'),

            'numSBBoards' => $blueprintsRepo->getNumberOfBoards(null, 'sbcanvas'),
            'numSBItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'sbcanvas'),

            'numRISKSBoards' => $blueprintsRepo->getNumberOfBoards(null, 'riskscanvas'),
            'numRISKSItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'riskscanvas'),

            'numEABoards' => $blueprintsRepo->getNumberOfBoards(null, 'eacanvas'),
            'numEAItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'eacanvas'),

            'numINSIGHTSBoards' => $blueprintsRepo->getNumberOfBoards(null, 'insightscanvas'),
            'numINSIGHTSItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'insightscanvas'),

            'numWikiBoards' => $blueprintsRepo->getNumberOfBoards(null, 'wiki'),
            'numWikiItems' => $blueprintsRepo->getNumberOfCanvasItems(null, 'wiki'),

            'numTaskSentimentAngry' => $taskSentiment['🤬'] ?? 0,
            'numTaskSentimentDisgust' => $taskSentiment['🤢'] ?? 0,
            'numTaskSentimentUnhappy' => $taskSentiment['🙁'] ?? 0,
            'numTaskSentimentNeutral' => $taskSentiment['😐'] ?? 0,
            'numTaskSentimentHappy' => $taskSentiment['🙂'] ?? 0,
            'numTaskSentimentLove' => $taskSentiment['😍'] ?? 0,
            'numTaskSentimenUnicorn' => $taskSentiment['🦄'] ?? 0,

            'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'phpUname' => php_uname(),
            'isDocker' => $this->isRunningInDocker(),
            'phpSapiName' => php_sapi_name(),
            'phpOs' => PHP_OS ?? 'unknown',

        ];

        $telemetry = self::dispatch_filter('beforeReturnTelemetry', $telemetry);

        return $telemetry;
    }

    /**
     * @throws BindingResolutionException
     *
     * @api
     */
    public function sendAnonymousTelemetry(): bool|PromiseInterface
    {

        // Only send once a day

        $allowTelemetry = app('config')->allowTelemetry ?? true;

        if ($allowTelemetry === true) {
            $date_utc = new DateTime('now', new DateTimeZone('UTC'));
            $today = $date_utc->format('Y-m-d');
            $lastUpdate = $this->settings->getSetting('companysettings.telemetry.lastUpdate');

            if ($lastUpdate != $today) {
                $telemetry = app()->call([$this, 'getAnonymousTelemetry']);
                $telemetry['date'] = $today;

                // Do the curl
                $httpClient = new Client;

                try {

                    $data_string = json_encode($telemetry);

                    $promise = $httpClient->postAsync('https://telemetry.leantime.io', [
                        'form_params' => [
                            'telemetry' => $data_string,
                        ],
                        // Short connect timeout so an offline/air-gapped server (or a
                        // CI runner with no egress) fails fast instead of blocking the
                        // dashboard's Welcome widget — and saturating PHP-FPM workers —
                        // for minutes. The previous 480s total timeout hung the page
                        // when telemetry was unreachable. (#3372/#3373)
                        'connect_timeout' => 2,
                        'timeout' => 5,
                    ])->then(function ($response) use ($today) {
                        $this->settings->saveSetting('companysettings.telemetry.lastUpdate', $today);
                    });

                    return $promise;

                } catch (\Exception $e) {
                    Log::error($e);

                    return false;
                }
            }
        }

        return false;
    }

    /**
     * @return false|void
     *
     * @throws Exception
     *
     * @api
     */
    public function optOutTelemetry()
    {
        $date_utc = new DateTime('now', new DateTimeZone('UTC'));
        $today = $date_utc->format('Y-m-d');

        $companyId = $this->settings->getCompanyId();

        $telemetry = [
            'date' => '',
            'companyId' => $companyId,
            'version' => $this->appSettings->appVersion,
            'language' => '',
            'numUsers' => 0,
            'lastUserLogin' => 0,
            'numProjects' => 0,
            'numClients' => 0,
            'numComments' => 0,
            'numMilestones' => 0,
            'numTickets' => 0,

            'numBoards' => 0,

            'numIdeaItems' => 0,
            'numHoursBooked' => 0,

            'numResearchBoards' => 0,
            'numResearchItems' => 0,

            'numRetroBoards' => 0,
            'numRetroItems' => 0,

            'numGoalBoards' => 0,
            'numGoalItems' => 0,

            'numValueCanvasBoards' => 0,
            'numValueCanvasItems' => 0,

            'numMinEmpathyBoards' => 0,
            'numMinEmpathyItems' => 0,

            'numOBMBoards' => 0,
            'numOBMItems' => 0,

            'numSWOTBoards' => 0,
            'numSWOTItems' => 0,

            'numSBBoards' => 0,
            'numSBItems' => 0,

            'numRISKSBoards' => 0,
            'numRISKSItems' => 0,

            'numEABoards' => 0,
            'numEAItems' => 0,

            'numINSIGHTSBoards' => 0,
        ];

        $telemetry['date'] = $today;

        // Do the curl
        $httpClient = new Client;

        try {
            $data_string = json_encode($telemetry);

            $promise = $httpClient->postAsync('https://telemetry.leantime.io', [
                'form_params' => [
                    'telemetry' => $data_string,
                ],
                'timeout' => 5,
            ])->then(function ($response) use ($today) {

                $this->settings->saveSetting('companysettings.telemetry.lastUpdate', $today);
                session(['skipTelemetry' => true]);
            });
        } catch (\Exception $e) {
            report($e);

            session(['skipTelemetry' => true]);

            return false;
        }

        $this->settings->saveSetting('companysettings.telemetry.active', false);

        session(['skipTelemetry' => true]);

        try {
            $promise->wait();
        } catch (\Exception $e) {
            report($e);
        }

    }

    /**
     * @return array
     *
     * @throws Exception
     *
     * @api
     */
    public function getProjectStatusReport()
    {

        $projectStatus = $this->projectRepository->getAll();

        $statusList = ['green' => 0, 'yellow' => 0, 'red' => 0, 'none' => 0];
        foreach ($projectStatus as $project) {
            if (isset($statusList[$project['status']])) {
                $statusList[$project['status']]++;
            } else {
                $statusList['none']++;
            }
        }

        return $statusList;
    }

    public function generateTicketReactionsReport()
    {
        $reactionsRepo = app()->make(Reactions::class);
        $collectedReactions = $reactionsRepo->getReactionsByModule('ticketSentiment');

        $reactions = [
            '🤬' => 0,
            '🤢' => 0,
            '🙁' => 0,
            '😐' => 0,
            '🙂' => 0,
            '😍' => 0,
            '🦄' => 0,
            'other' => 0,
        ];

        foreach ($collectedReactions as $reaction) {
            if (isset($reactions[$reaction['reaction']])) {
                $reactions[$reaction['reaction']] = $reactions[$reaction['reaction']] + $reaction['reactionCount'];
            }
        }

        return $reactions;
    }

    /**
     * Checks if Leantime is running in a Docker environment
     * Uses multiple detection methods and handles errors gracefully
     */
    private function isRunningInDocker(): bool
    {
        // Method 1: Check for /.dockerenv file
        try {
            if (is_file('/.dockerenv')) {
                return true;
            }
        } catch (\Exception $e) {
            // Silently fail if file access is restricted
        }

        // Method 2: Check for Docker-specific environment variables
        if (getenv('DOCKER_CONTAINER') !== false || getenv('IS_DOCKER') !== false) {
            return true;
        }

        // Method 3: Check cgroup info (works on Linux hosts)
        try {
            return strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false;
        } catch (\Exception $e) {
            return false; // Return false if all detection methods fail
        }
    }
}
