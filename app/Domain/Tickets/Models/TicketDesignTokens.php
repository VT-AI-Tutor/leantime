<?php

namespace Leantime\Domain\Tickets\Models;

/**
 * Design tokens for ticket visualization
 * Centralizes priority, effort, type, and status mappings
 */
class TicketDesignTokens
{
    /**
     * Priority levels with labels and color mappings
     */
    public const PRIORITIES = [
        1 => [
            'label' => 'Critical',
            'cssVar' => '--priority-critical',
            'color' => '#C73E5C',  // Design spec
            'icon' => 'thermometer-full',
            'fill' => 1.0,  // Thermometer fill level (0.0-1.0)
        ],
        2 => [
            'label' => 'High',
            'cssVar' => '--priority-high',
            'color' => '#E85A5A',  // Design spec
            'icon' => 'thermometer-three-quarters',
            'fill' => 0.8,
        ],
        3 => [
            'label' => 'Medium',
            'cssVar' => '--priority-medium',
            'color' => '#F5A623',  // Design spec
            'icon' => 'thermometer-half',
            'fill' => 0.6,
        ],
        4 => [
            'label' => 'Low',
            'cssVar' => '--priority-low',
            'color' => '#2ECC71',  // Design spec
            'icon' => 'thermometer-quarter',
            'fill' => 0.4,
        ],
        5 => [
            'label' => 'Lowest',
            'cssVar' => '--priority-lowest',
            'color' => '#6B7280',  // Design spec
            'icon' => 'thermometer-empty',
            'fill' => 0.2,
        ],
    ];

    /**
     * Effort/Story points with labels and size mappings
     */
    public const EFFORTS = [
        0.5 => ['label' => '30 phút', 'size' => 'xxs', 'tshirtLabel' => '30m'],
        1 => ['label' => '1 tiếng', 'size' => 'xs', 'tshirtLabel' => '1h'],
        2 => ['label' => '2 tiếng', 'size' => 'sm', 'tshirtLabel' => '2h'],
        4 => ['label' => '4 tiếng', 'size' => 'md', 'tshirtLabel' => '4h'],
        8 => ['label' => '1 ngày', 'size' => 'lg', 'tshirtLabel' => '1d'],
        12 => ['label' => '1.5 ngày', 'size' => 'xl', 'tshirtLabel' => '1.5d'],
        16 => ['label' => '2 ngày', 'size' => 'xxl', 'tshirtLabel' => '2d'],
    ];

    /**
     * Ticket types with emoji icons
     */
    public const TYPES = [
        'story' => ['label' => 'Story', 'icon' => '👤', 'faIcon' => 'fa-book'],
        'task' => ['label' => 'Task', 'icon' => '📋', 'faIcon' => 'fa-check-square'],
        'subtask' => ['label' => 'Subtask', 'icon' => '📋', 'faIcon' => 'fa-diagram-successor'],
        'bug' => ['label' => 'Bug', 'icon' => '🐛', 'faIcon' => 'fa-bug'],
        'feature' => ['label' => 'Feature', 'icon' => '✨', 'faIcon' => 'fa-star'],
        'epic' => ['label' => 'Epic', 'icon' => '🏔️', 'faIcon' => 'fa-mountain'],
        'documentation' => ['label' => 'Documentation', 'icon' => '📄', 'faIcon' => 'fa-file'],
        'improvement' => ['label' => 'Improvement', 'icon' => '🔧', 'faIcon' => 'fa-wrench'],
        'research' => ['label' => 'Research', 'icon' => '🔬', 'faIcon' => 'fa-flask'],
    ];

    /**
     * Get priority token by ID
     *
     * @param  int  $id  Priority ID (1-5)
     * @return array|null Priority configuration array or null if not found
     */
    public static function getPriority(int $id): ?array
    {
        return self::PRIORITIES[$id] ?? null;
    }

    /**
     * Get effort token by points
     *
     * @param  float  $points  Story points value
     * @return array|null Effort configuration array or null if not found
     */
    public static function getEffort(float $points): ?array
    {
        return self::EFFORTS[$points] ?? null;
    }

    /**
     * Get type token by name
     *
     * @param  string  $type  Ticket type name
     * @return array|null Type configuration array or null if not found
     */
    public static function getType(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }
}
