@extends($layout)

@section('content')

@php
    $canEdit = $canEdit ?? false;
@endphp

{{-- .maincontent normally uses margin-top:-95px to tuck under a .pageheader.
     This editor has no .pageheader, so we override it to clear the fixed header. --}}
<div class="maincontent" style="margin-top:60px;">
    <div class="maincontentinner" style="padding-top:15px;">
        {!! $tpl->displayNotification() !!}

        <div class="row" style="margin-bottom:12px; align-items:center;">
            <div class="col-md-7" style="display:flex; align-items:center; gap:12px;">
                <a href="{{ BASE_URL }}/whiteboards/showBoards" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> {{ __('buttons.back') }}
                </a>
                @if ($canEdit)
                    <input type="text" id="whiteboardTitle" value="{{ $tpl->escape($board['title']) }}"
                           maxlength="255"
                           style="margin-bottom:0; font-weight:bold; max-width:360px;" />
                    <span id="whiteboardSaveStatus" style="font-size:var(--font-size-xs); color:var(--lighter-font-color);"></span>
                @else
                    <h3 style="margin:0;">{{ $tpl->escape($board['title']) }}</h3>
                @endif
            </div>
            <div class="col-md-5">
                <div class="pull-right" style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" id="whiteboardFullscreen" class="btn btn-secondary">
                        <i class="fa fa-expand"></i> {{ __('buttons.fullscreen') }}
                    </button>
                    @if ($canEdit)
                        <a href="{{ BASE_URL }}/whiteboards/delete/{{ $board['id'] }}" class="btn btn-secondary delete">
                            <i class="fa fa-trash"></i> {{ __('links.delete') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div id="whiteboard-canvas-wrap"
             style="height: calc(100vh - 230px); min-height:500px; width:100%; border-radius:var(--box-radius); overflow:hidden; box-shadow:var(--regular-shadow); background:var(--primary-background, #fff); position:relative;">
            <div id="excalidraw-root"
                 data-board-id="{{ (int) $board['id'] }}"
                 data-can-edit="{{ $canEdit ? '1' : '0' }}"
                 data-view-mode="{{ $canEdit ? 'false' : 'true' }}"
                 style="height:100%; width:100%;">
            </div>
        </div>
    </div>
</div>

@push('scripts')
    {{-- Excalidraw is React-based and only loaded on this editor page (kept out of
         the global bundle). Loaded from unpkg; CSP allows unpkg via the cspParts
         filter in app/Domain/Whiteboards/register.php. The UMD bundle injects its
         own styles, so no separate stylesheet is needed; fonts/assets are served
         from EXCALIDRAW_ASSET_PATH (dist/excalidraw-assets/). --}}
    <script>window.EXCALIDRAW_ASSET_PATH = "https://unpkg.com/@excalidraw/excalidraw@0.17.6/dist/";</script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/@excalidraw/excalidraw@0.17.6/dist/excalidraw.production.min.js"></script>
    <style>
        /* Ensure the canvas fills the screen in fullscreen mode. */
        #whiteboard-canvas-wrap:fullscreen,
        #whiteboard-canvas-wrap:-webkit-full-screen {
            height: 100% !important;
            width: 100% !important;
            border-radius: 0;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.leantime && leantime.whiteboardController) {
                leantime.whiteboardController.init();
            }

            // Fullscreen toggle — bound inline so it works regardless of the
            // compiled-app bundle state.
            (function () {
                var btn = document.getElementById('whiteboardFullscreen');
                var wrap = document.getElementById('whiteboard-canvas-wrap');
                if (!btn || !wrap) {
                    return;
                }

                function fsElement() {
                    return document.fullscreenElement || document.webkitFullscreenElement || null;
                }

                btn.addEventListener('click', function () {
                    if (!fsElement()) {
                        var request = wrap.requestFullscreen || wrap.webkitRequestFullscreen || wrap.msRequestFullscreen;
                        if (request) {
                            var result = request.call(wrap);
                            if (result && typeof result.catch === 'function') {
                                result.catch(function (err) { console.error('Fullscreen failed:', err); });
                            }
                        } else {
                            console.error('Fullscreen API not supported by this browser.');
                        }
                    } else {
                        var exit = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
                        if (exit) {
                            exit.call(document);
                        }
                    }
                });

                function onChange() {
                    var icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = fsElement() ? 'fa fa-compress' : 'fa fa-expand';
                    }
                    // Nudge Excalidraw to recompute its canvas size.
                    window.dispatchEvent(new Event('resize'));
                }

                document.addEventListener('fullscreenchange', onChange);
                document.addEventListener('webkitfullscreenchange', onChange);
            })();
        });
    </script>
@endpush

@endsection
