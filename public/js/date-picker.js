(() => {
    "use strict";

    const DATE_SELECTOR = 'input[type="date"].form-control';
    const DISPLAY_SUFFIX = "-display";
    const INVALID_DATE_MESSAGE = "Informe uma data válida no formato dd/mm/aaaa.";
    const OUT_OF_RANGE_MESSAGE = "Escolha uma data dentro do período permitido.";

    if (typeof window.flatpickr !== "function") return;

    const findLabel = (input) => {
        if (!input.id) return null;
        return document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
    };

    const strictDate = (value) => {
        const match = value.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) return null;

        const [, day, month, year] = match.map(Number);
        const date = new Date(0);
        date.setHours(0, 0, 0, 0);
        date.setFullYear(year, month - 1, day);

        return date.getFullYear() === year &&
            date.getMonth() === month - 1 &&
            date.getDate() === day
            ? date
            : null;
    };

    const strictIsoDate = (value) => {
        const match = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return null;

        const [, year, month, day] = match.map(Number);
        const date = new Date(0);
        date.setHours(0, 0, 0, 0);
        date.setFullYear(year, month - 1, day);

        return date.getFullYear() === year &&
            date.getMonth() === month - 1 &&
            date.getDate() === day
            ? date
            : null;
    };

    const isWithinRange = (date, instance) => {
        const { _minDate: minDate, _maxDate: maxDate } = instance.config;
        return (!minDate || date >= minDate) && (!maxDate || date <= maxDate);
    };

    const labelCalendar = (instance, label) => {
        const month = instance.config.locale.months.longhand[instance.currentMonth];
        const year = instance.currentYear;
        const calendarLabel = label?.textContent?.trim()
            ? `Calendário para ${label.textContent.trim()}`
            : "Calendário";

        instance.calendarContainer.setAttribute("role", "dialog");
        instance.calendarContainer.setAttribute("aria-label", calendarLabel);
        instance.prevMonthNav?.setAttribute("aria-label", "Mês anterior");
        instance.nextMonthNav?.setAttribute("aria-label", "Próximo mês");
        instance.currentMonthElement?.setAttribute("aria-label", `${month} de ${year}`);
        instance.currentMonthElement?.setAttribute("aria-live", "polite");
    };

    const initializePicker = (input) => {
        if (input.dataset.datePickerReady === "true") return;
        input.dataset.datePickerReady = "true";

        const label = findLabel(input);
        const initialValue = input.value;
        let suppressNextFocus = false;
        let instance;

        const setInvalid = (message) => {
            instance.altInput.setCustomValidity(message);
            instance.altInput.setAttribute("aria-invalid", "true");
        };

        const setValid = () => {
            instance.altInput.setCustomValidity("");
            instance.altInput.removeAttribute("aria-invalid");
        };

        const clearSubmittedValue = () => {
            instance.input.value = "";
            instance.selectedDates = [];
            instance.latestSelectedDateObj = undefined;
            instance.redraw();
        };

        const validateDisplayValue = () => {
            const value = instance.altInput.value.trim();
            if (!value) {
                if (instance.input.value || instance.selectedDates.length) instance.clear(false);
                setValid();
                return true;
            }

            const date = strictDate(value);
            if (!date) {
                clearSubmittedValue();
                setInvalid(INVALID_DATE_MESSAGE);
                return false;
            }

            if (!isWithinRange(date, instance)) {
                clearSubmittedValue();
                setInvalid(OUT_OF_RANGE_MESSAGE);
                return false;
            }

            const selectedDate = instance.selectedDates[0];
            if (selectedDate &&
                instance.input.value &&
                instance.formatDate(selectedDate, "d/m/Y") === value) {
                setValid();
                return true;
            }

            setValid();
            instance.setDate(date, true);
            return true;
        };

        const focusDisplayInput = () => {
            suppressNextFocus = true;
            instance.altInput.focus({ preventScroll: true });
            window.setTimeout(() => {
                suppressNextFocus = false;
            });
        };

        instance = window.flatpickr(input, {
            altFormat: "d/m/Y",
            altInput: true,
            altInputClass: "date-picker-input",
            allowInput: true,
            appendTo: document.body,
            ariaDateFormat: "l, j de F de Y",
            closeOnSelect: true,
            dateFormat: "Y-m-d",
            disableMobile: true,
            locale: window.flatpickr.l10ns?.pt ?? "default",
            maxDate: input.max || undefined,
            minDate: input.min || undefined,
            monthSelectorType: "static",
            parseDate: (value, format) => format === "d/m/Y"
                ? strictDate(value)
                : strictIsoDate(value),
            onChange: (_dates, _value, picker) => {
                setValid();
                if (!picker.isOpen) return;

                window.setTimeout(() => {
                    picker.close();
                    focusDisplayInput();
                });
            },
            onMonthChange: (_dates, _value, picker) => labelCalendar(picker, label),
            onReady: (_dates, _value, picker) => labelCalendar(picker, label),
            onYearChange: (_dates, _value, picker) => labelCalendar(picker, label),
        });

        const display = instance.altInput;
        display.id = `${input.id || "date"}${DISPLAY_SUFFIX}`;
        display.classList.add("form-control");
        display.required = input.required;
        display.autocomplete = "off";
        display.inputMode = "text";
        display.placeholder = "dd/mm/aaaa";
        display.setAttribute("aria-haspopup", "dialog");
        display.setAttribute("aria-expanded", "false");
        display.setAttribute("aria-label", input.getAttribute("aria-label") || "Selecionar data");
        if (input.getAttribute("aria-describedby")) {
            display.setAttribute("aria-describedby", input.getAttribute("aria-describedby"));
        }
        if (input.getAttribute("aria-invalid") === "true") {
            display.setAttribute("aria-invalid", "true");
        }

        instance.calendarContainer.id = `${input.id || "date"}-calendar`;
        display.setAttribute("aria-controls", instance.calendarContainer.id);

        if (label) {
            label.htmlFor = display.id;
            display.setAttribute("aria-labelledby", label.id || "");
            if (!label.id) {
                label.id = `${input.id}-label`;
                display.setAttribute("aria-labelledby", label.id);
            }
        }

        const setExpanded = (expanded) => display.setAttribute("aria-expanded", String(expanded));
        instance.config.onOpen.push(() => setExpanded(true));
        instance.config.onClose.push(() => setExpanded(false));

        const ignoreNextDocumentFocus = (target) => {
            if (!(target instanceof Element)) return;
            instance.config.ignoredFocusElements.push(target);
            window.setTimeout(() => {
                const index = instance.config.ignoredFocusElements.indexOf(target);
                if (index !== -1) instance.config.ignoredFocusElements.splice(index, 1);
            });
        };

        const closeInvalidDraft = (nextFocusTarget) => {
            ignoreNextDocumentFocus(nextFocusTarget);
            instance.close();
        };

        display.addEventListener("focus", (event) => {
            if (suppressNextFocus) event.stopImmediatePropagation();
        }, true);

        display.addEventListener("input", () => {
            if (!display.value.trim()) {
                instance.clear(false);
                setValid();
                return;
            }

            if (strictDate(display.value)) validateDisplayValue();
            else {
                clearSubmittedValue();
                setInvalid(INVALID_DATE_MESSAGE);
            }
        });

        display.addEventListener("blur", (event) => {
            if (event.relatedTarget && instance.calendarContainer.contains(event.relatedTarget)) return;
            if (validateDisplayValue()) return;
            closeInvalidDraft(event.relatedTarget);
            event.stopImmediatePropagation();
        }, true);
        display.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown" && instance.isOpen) {
                const selected = instance.selectedDateElem;
                const firstEnabledDay = instance.daysContainer?.querySelector(
                    ".flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)",
                );
                (selected || firstEnabledDay)?.focus();
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (event.key === "Escape" && instance.isOpen) {
                instance.close();
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (event.key === "Enter" && !validateDisplayValue()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                display.reportValidity();
            }
        }, true);
        display.addEventListener("invalid", () => {
            display.setAttribute("aria-invalid", "true");
        });

        const preventInvalidDocumentParse = (event) => {
            const target = event.target;
            if (!instance.isOpen || document.activeElement !== display ||
                target === display || !(target instanceof Element) ||
                instance.calendarContainer.contains(target) || validateDisplayValue()) return;

            ignoreNextDocumentFocus(target);
            instance.close();
        };
        document.addEventListener("mousedown", preventInvalidDocumentParse, true);
        document.addEventListener("touchstart", preventInvalidDocumentParse, true);

        const form = input.form;
        form?.addEventListener("reset", () => {
            window.setTimeout(() => {
                input.value = initialValue;
                instance.setDate(initialValue, false, "Y-m-d");
                setValid();
            });
        });

        form?.addEventListener("submit", (event) => {
            if (validateDisplayValue()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            display.reportValidity();
            display.focus({ preventScroll: true });
        }, true);

        window.addEventListener("pageshow", () => {
            instance.setDate(input.value, false, "Y-m-d");
            setValid();
        });
    };

    const initialize = () => document.querySelectorAll(DATE_SELECTOR).forEach(initializePicker);

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, { once: true });
    else initialize();
})();
