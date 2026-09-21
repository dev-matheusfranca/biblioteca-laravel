(() => {
    const toggle = document.querySelector(".menu-toggle");
    const setMenu = (open) => {
        document.body.classList.toggle("menu-open", open);
        toggle?.setAttribute("aria-expanded", String(open));
        toggle?.setAttribute("aria-label", open ? "Fechar menu" : "Abrir menu");
    };
    toggle?.addEventListener("click", () =>
        setMenu(toggle.getAttribute("aria-expanded") !== "true"),
    );
    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            document.body.classList.contains("menu-open")
        ) {
            setMenu(false);
            toggle?.focus();
        }
    });
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".sidebar, .menu-toggle")) setMenu(false);
    });
    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (event.defaultPrevented) return;
            const confirmation =
                event.submitter?.dataset.confirm || form.dataset.confirm;
            if (confirmation && !window.confirm(confirmation)) {
                event.preventDefault();
                return;
            }
            if (form.dataset.submitting) {
                event.preventDefault();
                return;
            }
            const button = event.submitter;
            if (button && form.method.toLowerCase() === "post") {
                form.dataset.submitting = "true";
                button.dataset.originalText = button.textContent;
                button.textContent = "Aguarde…";
                button.disabled = true;
                form.setAttribute("aria-busy", "true");
            }
        });
    });
    window.addEventListener("pageshow", () => {
        document.querySelectorAll("form[data-submitting]").forEach((form) => {
            delete form.dataset.submitting;
            form.removeAttribute("aria-busy");
            form.querySelectorAll("[data-original-text]").forEach((button) => {
                button.textContent = button.dataset.originalText;
                button.disabled = false;
            });
        });
    });
    document.querySelector("[data-validation-summary]")?.focus();
})();
