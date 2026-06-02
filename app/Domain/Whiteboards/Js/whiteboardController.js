var leantime = leantime || {};

/**
 * Whiteboard controller.
 *
 * Mounts the Excalidraw React component into #excalidraw-root. The scene is
 * persisted LOCALLY in the browser's localStorage (keyed by board id) — it is
 * intentionally NOT shared across users/devices and is not written to the DB.
 * Only board metadata (title, list, delete) lives in the DB.
 *
 * Excalidraw / React / ReactDOM are loaded from CDN only on the editor page
 * (see whiteboards/show.blade.php), so this controller no-ops everywhere else.
 */
leantime.whiteboardController = (function () {

    var SCENE_STORAGE_PREFIX = 'leantime.excalidraw.scene.';

    var excalidrawAPI = null;
    var boardId = null;
    var canEdit = false;
    var saveTimer = null;
    var hydrating = true; // ignore the onChange that fires during initial render

    function setStatus(text) {
        var el = document.getElementById('whiteboardSaveStatus');
        if (el) {
            el.textContent = text;
        }
    }

    function scheduleSave() {
        if (!canEdit || hydrating) {
            return;
        }
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        setStatus('…');
        saveTimer = setTimeout(save, 1500);
    }

    function save() {
        if (!excalidrawAPI || !canEdit) {
            return;
        }

        var scene = JSON.stringify({
            elements: excalidrawAPI.getSceneElements(),
            // Only persist non-volatile view state. Excalidraw's appState also
            // holds transient UI/collaborator state we don't want to store.
            appState: {
                viewBackgroundColor: excalidrawAPI.getAppState().viewBackgroundColor,
                gridSize: excalidrawAPI.getAppState().gridSize,
            },
            files: excalidrawAPI.getFiles(),
        });

        try {
            localStorage.setItem(SCENE_STORAGE_PREFIX + boardId, scene);
            setStatus('✓');
        } catch (e) {
            // localStorage is capped (~5–10MB per origin) — large embedded
            // images can exceed it. Surface it instead of failing silently.
            setStatus('⚠');
            console.error('Local save failed (storage quota exceeded?):', e);
        }
    }

    var LIBRARY_STORAGE_KEY = 'leantime.excalidraw.library';

    // Excalidraw's embedded component does not persist the user's library on its
    // own (only excalidraw.com does). We persist it per-browser in localStorage so
    // "Add to library" survives reloads, mirroring the standalone app's behavior.
    function loadLibrary() {
        try {
            return JSON.parse(localStorage.getItem(LIBRARY_STORAGE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveLibrary(items) {
        try {
            localStorage.setItem(LIBRARY_STORAGE_KEY, JSON.stringify(items || []));
        } catch (e) { /* quota / disabled storage — non-fatal */ }
    }

    // Handles the "Add to Excalidraw" flow from libraries.excalidraw.com, which
    // redirects back to this page with a "#addLibrary=<url>" hash. We fetch and
    // merge that library (CSP must allow the library host — see register.php).
    function handleAddLibraryFromHash() {
        if (!excalidrawAPI || !canEdit) {
            return;
        }
        var hash = window.location.hash || '';
        if (hash.indexOf('addLibrary=') === -1) {
            return;
        }
        try {
            var params = new URLSearchParams(hash.replace(/^#/, ''));
            var libUrl = params.get('addLibrary');
            if (libUrl) {
                excalidrawAPI.updateLibrary({
                    libraryItems: libUrl,
                    merge: true,
                    openLibraryMenu: true,
                }).catch(function (err) { console.error('Add library failed:', err); });
                // Clean the hash so a reload doesn't re-import.
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
        } catch (e) {
            console.error('Add library failed:', e);
        }
    }

    function buildInitialData(sceneString) {
        if (!sceneString || sceneString.trim() === '' || sceneString.trim() === '{}') {
            return null;
        }
        try {
            var parsed = JSON.parse(sceneString);
            return {
                elements: parsed.elements || [],
                appState: parsed.appState || {},
                files: parsed.files || {},
                scrollToContent: true,
            };
        } catch (e) {
            return null;
        }
    }

    function render(initialData, viewMode) {
        var root = document.getElementById('excalidraw-root');

        // Seed the persisted personal library into the scene.
        initialData = initialData || {};
        initialData.libraryItems = loadLibrary();

        var props = {
            initialData: initialData,
            viewModeEnabled: viewMode,
            UIOptions: { canvasActions: { loadScene: false } },
            excalidrawAPI: function (api) {
                excalidrawAPI = api;
                handleAddLibraryFromHash();
            },
            onChange: function () { scheduleSave(); },
            onLibraryChange: function (items) {
                if (canEdit) {
                    saveLibrary(items);
                }
            },
        };

        var reactRoot = ReactDOM.createRoot(root);
        reactRoot.render(React.createElement(window.ExcalidrawLib.Excalidraw, props));

        // The initial render triggers onChange; release the guard afterwards.
        setTimeout(function () { hydrating = false; }, 1000);
    }

    function bindTitleRename() {
        var titleEl = document.getElementById('whiteboardTitle');
        if (!titleEl || !canEdit) {
            return;
        }
        titleEl.addEventListener('change', async function () {
            try {
                await leantime.rpc('Whiteboards.Whiteboards.renameBoard', { id: boardId, title: titleEl.value });
            } catch (e) { /* non-fatal */ }
        });
    }

    async function init() {
        var root = document.getElementById('excalidraw-root');
        if (!root || !window.ExcalidrawLib || !window.React || !window.ReactDOM) {
            return;
        }

        boardId = parseInt(root.dataset.boardId, 10);
        canEdit = root.dataset.canEdit === '1';
        var viewMode = root.dataset.viewMode === 'true';

        var sceneString = '';
        try {
            sceneString = localStorage.getItem(SCENE_STORAGE_PREFIX + boardId) || '';
        } catch (e) {
            sceneString = '';
        }

        render(buildInitialData(sceneString), viewMode);
        bindTitleRename();

        // Flush pending autosave when leaving the page.
        window.addEventListener('beforeunload', function () {
            if (saveTimer) {
                clearTimeout(saveTimer);
                save();
            }
        });
    }

    return {
        init: init,
    };
})();
