<?php

use Leantime\Core\Events\EventDispatcher;

/**
 * Whiteboards domain registration.
 *
 * The whiteboard editor loads Excalidraw (React + Excalidraw bundle) from
 * unpkg.com. The global CSP (app/Core/Middleware/InitialHeaders.php) already
 * allows unpkg.com for `script-src` and `font-src`, but Excalidraw also needs:
 *   - its stylesheet  -> `style-src`   (currently falls back to default-src 'self')
 *   - runtime fetches -> `connect-src` (currently falls back to default-src 'self')
 *   - blob web workers -> `worker-src` (currently falls back to script-src)
 *
 * The `connect-src` hosts also cover the Library feature: importing a library
 * from libraries.excalidraw.com fetches the .excalidrawlib JSON (served from
 * libraries.excalidraw.com / *.githubusercontent.com).
 *
 * We add the three missing directives here (additive only; each directive is
 * absent from the base policy, so no duplicates are produced).
 */
EventDispatcher::add_filter_listener(
    'leantime.core.middleware.initialheaders.handle.cspParts',
    function ($cspParts) {
        $cspParts[] = "style-src 'self' 'unsafe-inline' unpkg.com";
        $cspParts[] = "connect-src 'self' unpkg.com libraries.excalidraw.com *.githubusercontent.com data: blob:";
        $cspParts[] = "worker-src 'self' blob:";

        return $cspParts;
    }
);
