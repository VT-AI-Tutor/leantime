<?php

namespace Leantime\Domain\Whiteboards\Models;

/**
 * Whiteboard model - represents a single Excalidraw whiteboard belonging to a project.
 */
class Whiteboard
{
    /** @var int|string Whiteboard id */
    public int|string $id = '';

    /** @var string Whiteboard title */
    public string $title = '';

    /** @var int Project the whiteboard belongs to */
    public int $projectId = 0;

    /** @var int Author (user id) */
    public int $author = 0;

    /**
     * @var string Serialized Excalidraw scene (JSON string with elements, appState and files).
     */
    public string $scene = '';

    /** @var string|null Creation timestamp (UTC, Y-m-d H:i:s) */
    public ?string $created = null;

    /** @var string|null Last modified timestamp (UTC, Y-m-d H:i:s) */
    public ?string $modified = null;
}
