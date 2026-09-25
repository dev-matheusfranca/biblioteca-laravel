(() => {
    if (typeof window.TomSelect !== "function") return;

    document.querySelectorAll("select.form-control:not([multiple]):not([data-native-select])").forEach((select) => {
        if (select.tomselect) return;

        const picker = new window.TomSelect(select, {
            allowEmptyOption: true,
            create: false,
            maxOptions: null,
            hideSelected: false,
            closeAfterSelect: true,
            dropdownParent: "body",
            copyClassesToDropdown: false,
            sortField: [{ field: "$order" }, { field: "$score" }],
            render: {
                no_results: () => '<div class="no-results" role="status">Nenhuma opção encontrada.</div>',
            },
        });
        picker.wrapper.classList.add("biblioteca-select");
        picker.dropdown.classList.add("biblioteca-select-menu");
        select.setAttribute("aria-hidden", "true");
        picker.focus_node.setAttribute("aria-required", String(select.required));
        if (select.getAttribute("aria-describedby")) {
            picker.focus_node.setAttribute("aria-describedby", select.getAttribute("aria-describedby"));
        }
        if (select.getAttribute("aria-invalid") === "true") {
            picker.focus_node.setAttribute("aria-invalid", "true");
        }

        // Menus live outside overflow-hidden panels and fit above or below the field.
        picker.positionDropdown = () => {
            const rect = picker.control.getBoundingClientRect();
            const viewport = window.visualViewport;
            const viewportTop = viewport?.offsetTop || 0;
            const viewportLeft = viewport?.offsetLeft || 0;
            const viewportHeight = viewport?.height || window.innerHeight;
            const viewportWidth = viewport?.width || document.documentElement.clientWidth;
            const below = viewportTop + viewportHeight - rect.bottom - 16;
            const above = rect.top - viewportTop - 16;
            const openAbove = below < 200 && above > below;
            const available = Math.max(80, openAbove ? above : below);
            picker.dropdown_content.style.maxHeight = `${Math.min(260, available)}px`;
            const width = Math.min(rect.width, viewportWidth - 24);
            const left = Math.max(viewportLeft + 12, Math.min(rect.left, viewportLeft + viewportWidth - width - 12));
            Object.assign(picker.dropdown.style, {
                width: `${width}px`,
                left: `${left + window.scrollX}px`,
                top: `${(openAbove ? rect.top - picker.dropdown.offsetHeight - 6 : rect.bottom + 6) + window.scrollY}px`,
            });
            picker.dropdown.classList.toggle("opens-above", openAbove);
        };
        picker.on("dropdown_open", picker.positionDropdown);
        picker.on("type", () => requestAnimationFrame(picker.positionDropdown));
        const reposition = () => { if (picker.isOpen) picker.positionDropdown(); };
        document.addEventListener("scroll", reposition, { capture: true, passive: true });
        window.visualViewport?.addEventListener("resize", reposition);
        window.visualViewport?.addEventListener("scroll", reposition);

        // Keep required validation on the real select; show its message at the visible control.
        select.addEventListener("invalid", (event) => {
            event.preventDefault();
            picker.focus_node.setAttribute("aria-invalid", "true");
            if (select.form?.querySelector(":invalid") !== select) return;
            picker.focus_node.setCustomValidity(select.validationMessage);
            picker.focus();
            picker.focus_node.reportValidity();
        });
        picker.on("change", () => {
            picker.focus_node.setCustomValidity("");
            picker.focus_node.setAttribute("aria-invalid", String(!select.validity.valid));
        });
        select.form?.addEventListener("reset", () => {
            setTimeout(() => {
                picker.sync();
                picker.focus_node.setCustomValidity("");
                picker.focus_node.removeAttribute("aria-invalid");
            }, 0);
        });
        window.addEventListener("pageshow", () => picker.sync());
    });
})();
