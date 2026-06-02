/**
 * Generic checklist widget controller.
 *
 * Manages a client-side list of {title, done} items rendered by the
 * <x-global::checklist> component. The canonical state is serialized as JSON
 * into a hidden input so it is submitted together with the surrounding form
 * (no dedicated endpoint required - it is persisted in the entity's own column).
 *
 * All handlers are delegated from `document`, so widgets injected later (e.g.
 * inside an AJAX/nyroModal dialog) work without any per-instance init step.
 */
leantime.checklistController = (function () {

    function widgetOf(el) {
        return el.closest("[data-checklist]");
    }

    function isReadonly(widget) {
        return widget.getAttribute("data-readonly") === "1";
    }

    /**
     * Read the current DOM state of a widget and write it back to the hidden
     * input as JSON, then refresh the progress counter.
     */
    function serialize(widget) {
        var items = [];
        widget.querySelectorAll("[data-checklist-item]").forEach(function (li) {
            var textEl = li.querySelector("[data-checklist-text]");
            var toggleEl = li.querySelector("[data-checklist-toggle]");
            var title = textEl ? textEl.value.trim() : "";
            if (title === "") {
                return;
            }
            items.push({ title: title, done: toggleEl ? toggleEl.checked : false });
        });

        var input = widget.querySelector("[data-checklist-input]");
        if (input) {
            input.value = JSON.stringify(items);
        }

        updateProgress(widget, items);
    }

    function updateProgress(widget, items) {
        var done = items.filter(function (i) { return i.done; }).length;
        var progress = widget.querySelector("[data-checklist-progress]");
        if (progress) {
            progress.textContent = done + "/" + items.length;
        }
    }

    function createItem(title, done) {
        var li = document.createElement("li");
        li.className = "checklist-item" + (done ? " is-done" : "");
        li.setAttribute("data-checklist-item", "");

        var toggle = document.createElement("input");
        toggle.type = "checkbox";
        toggle.setAttribute("data-checklist-toggle", "");
        toggle.checked = !!done;

        var text = document.createElement("input");
        text.type = "text";
        text.className = "checklist-item-text";
        text.setAttribute("data-checklist-text", "");
        text.value = title;

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "checklist-remove";
        remove.setAttribute("data-checklist-remove", "");
        remove.innerHTML = '<i class="fa fa-times"></i>';

        li.appendChild(toggle);
        li.appendChild(text);
        li.appendChild(remove);
        return li;
    }

    function addItem(widget) {
        var newInput = widget.querySelector("[data-checklist-new]");
        if (!newInput) {
            return;
        }
        var title = (newInput.value || "").trim();
        if (title !== "") {
            var list = widget.querySelector("[data-checklist-items]");
            if (list) {
                list.appendChild(createItem(title, false));
                serialize(widget);
            }
        }
        newInput.value = "";
        newInput.focus();
    }

    // ---- Delegated handlers (bound once, survive modal injection) ----------

    document.addEventListener("click", function (e) {
        var addBtn = e.target.closest("[data-checklist-add-btn]");
        if (addBtn) {
            var w = widgetOf(addBtn);
            if (w && !isReadonly(w)) {
                e.preventDefault();
                addItem(w);
            }
            return;
        }

        var remove = e.target.closest("[data-checklist-remove]");
        if (remove) {
            var rw = widgetOf(remove);
            if (rw && !isReadonly(rw)) {
                e.preventDefault();
                var li = remove.closest("[data-checklist-item]");
                if (li) {
                    li.remove();
                }
                serialize(rw);
            }
        }
    });

    document.addEventListener("change", function (e) {
        if (e.target.matches("[data-checklist-toggle]")) {
            var w = widgetOf(e.target);
            if (!w) {
                return;
            }
            var li = e.target.closest("[data-checklist-item]");
            if (li) {
                li.classList.toggle("is-done", e.target.checked);
            }
            serialize(w);
        }
    });

    document.addEventListener("input", function (e) {
        if (e.target.matches("[data-checklist-text]")) {
            var w = widgetOf(e.target);
            if (w) {
                serialize(w);
            }
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Enter") {
            return;
        }
        if (e.target.matches("[data-checklist-new]")) {
            e.preventDefault();
            var w = widgetOf(e.target);
            if (w && !isReadonly(w)) {
                addItem(w);
            }
        } else if (e.target.matches("[data-checklist-text]")) {
            // Do not submit the surrounding form on Enter inside an item.
            e.preventDefault();
            var w2 = widgetOf(e.target);
            if (w2) {
                var newInput = w2.querySelector("[data-checklist-new]");
                if (newInput) {
                    newInput.focus();
                }
            }
        }
    });

    // Kept for backwards compatibility with templates that still call it.
    function init() {}

    return {
        init: init,
    };
})();
