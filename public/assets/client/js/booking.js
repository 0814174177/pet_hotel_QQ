document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("booking-app");

    if (!root) {
        return;
    }

    const data = JSON.parse(root.dataset.booking || "{}");
    const services = Array.isArray(data.services) ? data.services : [];
    const servicesById = new Map(
        services.map((service) => [String(service.id), service]),
    );

    const state = {
        room: null,
        checkin: null,
        checkout: null,
        calendarMonth: null,
        selectedPets: new Map(),
        petServices: {},
        activePetId: null,
    };

    const roomCards = document.querySelectorAll(".booking-room-card-v2");
    const datePanel = document.getElementById("bookingDatePanel");
    const petPanel = document.getElementById("bookingPetPanel");
    const calendarGrid = document.getElementById("bookingCalendarGrid");
    const calendarMonthLabel = document.getElementById(
        "bookingCalendarMonthLabel",
    );
    const prevMonthBtn = document.getElementById("bookingPrevMonthBtn");
    const nextMonthBtn = document.getElementById("bookingNextMonthBtn");
    const calendarRoom = document.getElementById("bookingCalendarRoom");
    const dateMessage = document.getElementById("bookingDateMessage");
    const checkinInput = document.getElementById("checkinDisplay");
    const checkoutInput = document.getElementById("checkoutDisplay");
    const petSelectList = document.getElementById("petSelectList");
    const addPetBtn = document.getElementById("addPetBtn");
    const bookingForm = document.getElementById("bookingForm");
    const bookingAlert = document.getElementById("bookingAlert");
    const serviceModal = document.getElementById("serviceModal");
    const serviceModalList = document.getElementById("serviceModalList");
    const servicePetName = document.getElementById("servicePetName");
    const saveServiceBtn = document.getElementById("saveServiceBtn");
    const hiddenFields = document.getElementById("bookingHiddenFields");
    const roomTypeInput = document.getElementById("bookingRoomType");
    const checkinHidden = document.getElementById("bookingCheckinValue");
    const checkoutHidden = document.getElementById("bookingCheckoutValue");
    const bookingSubmitBtn = document.getElementById("bookingSubmitBtn");
    const summaryActionHint = document.getElementById("summaryActionHint");
    const datePrerequisite = document.getElementById("bookingDatePrerequisite");
    const bookingHoldBtn = document.getElementById("bookingHoldBtn");
    const bookingActionInput = document.getElementById("bookingAction");

    const today = parseIsoDate(data.today) || normalizeDate(new Date());
    const isAuthenticated = Boolean(data.isAuthenticated);
    const todayIso = toIsoDate(today);

    checkinInput.min = todayIso;
    checkoutInput.min = todayIso;

    function formatCurrency(value) {
        return new Intl.NumberFormat("vi-VN").format(Number(value || 0)) + "đ";
    }

    function normalizeDate(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function parseIsoDate(value) {
        if (!value) {
            return null;
        }

        const parts = String(value).split("-").map(Number);

        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function toIsoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }

    function formatDateVN(date) {
        const day = String(date.getDate()).padStart(2, "0");
        const month = String(date.getMonth() + 1).padStart(2, "0");

        return `${day}/${month}/${date.getFullYear()}`;
    }

    function addDays(date, days) {
        const next = new Date(date);
        next.setDate(next.getDate() + days);
        return normalizeDate(next);
    }

    function startOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1);
    }

    function addMonths(date, months) {
        return new Date(date.getFullYear(), date.getMonth() + months, 1);
    }

    function sameMonth(first, second) {
        return (
            first &&
            second &&
            first.getFullYear() === second.getFullYear() &&
            first.getMonth() === second.getMonth()
        );
    }

    function diffDays(start, end) {
        if (!start || !end || end <= start) {
            return 0;
        }

        return Math.round((end - start) / 86400000);
    }

    function formatStaySummary() {
        return [
            `Ngày nhận phòng: ${formatDateVN(state.checkin)}`,
            `Ngày trả phòng: ${formatDateVN(state.checkout)}`,
            `Số đêm: ${diffDays(state.checkin, state.checkout)} đêm`,
        ].join("\n");
    }

    function sameDate(first, second) {
        return first && second && toIsoDate(first) === toIsoDate(second);
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function getUnavailableSet() {
        if (!state.room) {
            return new Set();
        }

        const unavailable =
            data.availability?.[state.room.id]?.unavailable || [];

        return new Set(unavailable.map(String));
    }

    function isPast(date) {
        return date < today;
    }

    function isUnavailable(date) {
        return getUnavailableSet().has(toIsoDate(date));
    }

    function isBookableDate(date) {
        return !isPast(date) && !isUnavailable(date);
    }

    function rangeHasBlocked(start, end) {
        if (!start || !end || end <= start) {
            return true;
        }

        for (let date = new Date(start); date <= end; date = addDays(date, 1)) {
            if (isPast(date) || isUnavailable(date)) {
                return true;
            }
        }

        return false;
    }

    function showDateMessage(message, isWarning = false, isReady = false) {
        dateMessage.textContent = message;
        dateMessage.classList.toggle("warning", isWarning);
        dateMessage.classList.toggle("ready", isReady);
    }

    function clearSelectedDate(date) {
        const clearsCheckin = sameDate(date, state.checkin);
        const clearsCheckout = sameDate(date, state.checkout);

        if (!clearsCheckin && !clearsCheckout) {
            return false;
        }

        if (clearsCheckin) {
            state.checkin = null;
        }

        if (clearsCheckout) {
            state.checkout = null;
        }

        if (state.checkin && state.checkout) {
            showDateMessage(formatStaySummary(), false, true);
        } else if (state.checkin) {
            showDateMessage(
                "Đã bỏ chọn ngày trả phòng. Vui lòng chọn ngày trả phòng mới.",
            );
        } else if (state.checkout) {
            showDateMessage(
                "Đã bỏ chọn ngày nhận phòng. Vui lòng chọn lại ngày nhận phòng.",
            );
        } else {
            showDateMessage(
                "Đã bỏ chọn ngày lưu trú. Vui lòng chọn ngày nhận và ngày trả phòng.",
            );
        }

        return true;
    }

    function selectRoom(card) {
        if (card.disabled || card.classList.contains("room-sold-out")) {
            return;
        }

        roomCards.forEach((item) => {
            item.classList.remove("active");
            item.setAttribute("aria-pressed", "false");
        });

        card.classList.add("active");
        card.setAttribute("aria-pressed", "true");

        state.room = {
            id: card.dataset.roomId,
            name: card.dataset.roomName,
            price: Number(card.dataset.roomPrice || 0),
            maxPets: Number(card.dataset.roomMaxPets || 1),
            maxWeight: Number(card.dataset.roomMaxWeight || 0),
        };

        roomTypeInput.value = state.room.id;
        calendarRoom.textContent = state.room.name;
        state.calendarMonth = startOfMonth(state.checkin || today);
        datePrerequisite?.classList.add("is-hidden");
        datePanel.hidden = false;

        const checkinBlocked = state.checkin && !isBookableDate(state.checkin);
        const checkoutBlocked =
            state.checkout && !isBookableDate(state.checkout);

        if (checkinBlocked || checkoutBlocked) {
            if (checkinBlocked) {
                state.checkin = null;
            }

            if (checkoutBlocked) {
                state.checkout = null;
            }

            showDateMessage(
                "Ngày đã chọn không còn phù hợp với loại phòng này. Vui lòng chọn ngày màu xanh còn trống.",
                true,
            );
            syncDateInputs();
        } else if (
            state.checkin &&
            state.checkout &&
            rangeHasBlocked(state.checkin, state.checkout)
        ) {
            state.checkout = null;
            showDateMessage(
                "Khoảng thời gian này không còn phòng trống. Vui lòng chọn ngày khác.",
                true,
            );
            syncDateInputs();
        } else if (state.checkin && state.checkout) {
            showDateMessage(formatStaySummary(), false, true);
        }

        resetInvalidPets();
        renderCalendar();
        updateStepVisibility();
        updateSummary();
    }

    function renderCalendar() {
        if (!state.room) {
            return;
        }

        if (!state.calendarMonth) {
            state.calendarMonth = startOfMonth(state.checkin || today);
        }

        const monthStart = startOfMonth(state.calendarMonth);
        const year = monthStart.getFullYear();
        const month = monthStart.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDayOffset = (monthStart.getDay() + 6) % 7;
        const totalCells = Math.ceil((firstDayOffset + daysInMonth) / 7) * 7;

        calendarGrid.innerHTML = "";
        calendarMonthLabel.textContent = `Tháng ${String(month + 1).padStart(2, "0")}/${year}`;

        if (prevMonthBtn) {
            prevMonthBtn.disabled =
                sameMonth(monthStart, startOfMonth(today)) ||
                monthStart < startOfMonth(today);
        }

        for (let index = 0; index < totalCells; index += 1) {
            const dayNumber = index - firstDayOffset + 1;

            if (dayNumber < 1 || dayNumber > daysInMonth) {
                const emptyCell = document.createElement("span");
                emptyCell.className = "booking-day-empty";
                calendarGrid.appendChild(emptyCell);
                continue;
            }

            const date = new Date(year, month, dayNumber);
            const button = document.createElement("button");
            const iso = toIsoDate(date);
            const unavailable = isUnavailable(date);
            const past = isPast(date);
            const bookable = !past && !unavailable;
            const selectedAsCheckin = sameDate(date, state.checkin);
            const selectedAsCheckout = sameDate(date, state.checkout);
            const selected = selectedAsCheckin || selectedAsCheckout;
            const inRange =
                state.checkin &&
                state.checkout &&
                date > state.checkin &&
                date < state.checkout;
            let statusLabel = "Còn trống, có thể chọn";

            if (past) {
                statusLabel = "Đã qua hoặc không thể đặt";
            } else if (unavailable) {
                statusLabel = "Kín lịch, không thể chọn";
            } else if (selectedAsCheckin && selectedAsCheckout) {
                statusLabel = "Đang chọn làm ngày nhận và ngày trả phòng";
            } else if (selectedAsCheckin) {
                statusLabel = "Đang chọn làm ngày nhận phòng";
            } else if (selectedAsCheckout) {
                statusLabel = "Đang chọn làm ngày trả phòng";
            } else if (inRange) {
                statusLabel = "Nằm trong khoảng lưu trú đã chọn";
            }

            button.type = "button";
            button.className = "booking-day";
            button.dataset.date = iso;
            button.setAttribute(
                "aria-label",
                `${formatDateVN(date)} - ${statusLabel}`,
            );
            button.innerHTML = `<span>${dayNumber}</span>`;

            if (sameDate(date, today)) {
                button.classList.add("is-today");
            }

            if (bookable) {
                button.classList.add("is-available");
            }

            if (past) {
                button.classList.add("is-past");
            }

            if (unavailable) {
                button.classList.add("is-unavailable");
            }

            if (selected && bookable) {
                button.classList.add("is-selected");
            } else if (inRange && bookable) {
                button.classList.add("is-range");
            }

            button.disabled = !bookable;
            button.title = statusLabel;
            button.setAttribute("aria-disabled", String(!bookable));

            if (!button.disabled) {
                button.addEventListener("click", function () {
                    handleDatePick(date);
                });
            }

            calendarGrid.appendChild(button);
        }
    }

    function handleDatePick(date) {
        if (!state.room) {
            showDateMessage(
                "Vui lòng chọn loại phòng trước khi xem lịch trống.",
                true,
            );
            return;
        }

        if (!isBookableDate(date)) {
            showDateMessage(
                "Ngày này không thể chọn. Vui lòng chọn ô màu xanh còn trống.",
                true,
            );
            return;
        }

        if (clearSelectedDate(date)) {
            syncDateInputs();
            renderCalendar();
            updateStepVisibility();
            updateSummary();
            return;
        }

        if (!state.checkin || state.checkout) {
            state.checkin = date;
            state.checkout = null;
            showDateMessage(
                "Đã chọn ngày nhận phòng, tiếp tục chọn ngày trả phòng.",
            );
        } else if (date <= state.checkin) {
            state.checkout = null;
            showDateMessage("Ngày trả phòng phải sau ngày nhận phòng.", true);
        } else if (rangeHasBlocked(state.checkin, date)) {
            state.checkout = null;
            showDateMessage(
                "Khoảng thời gian này không còn phòng trống. Vui lòng chọn ngày khác.",
                true,
            );
        } else {
            state.checkout = date;
            showDateMessage(formatStaySummary(), false, true);
        }

        syncDateInputs();
        renderCalendar();
        updateStepVisibility();
        updateSummary();
    }

    function syncDateInputs() {
        checkinInput.value = state.checkin ? toIsoDate(state.checkin) : "";
        checkoutInput.value = state.checkout ? toIsoDate(state.checkout) : "";
        checkinHidden.value = state.checkin
            ? `${toIsoDate(state.checkin)} 09:00:00`
            : "";
        checkoutHidden.value = state.checkout
            ? `${toIsoDate(state.checkout)} 17:00:00`
            : "";
    }

    function handleManualDateChange() {
        state.checkin = parseIsoDate(checkinInput.value);
        state.checkout = parseIsoDate(checkoutInput.value);
        const checkinBlocked = state.checkin && !isBookableDate(state.checkin);
        const checkoutBlocked =
            state.checkout && !isBookableDate(state.checkout);

        if (!state.room) {
            state.checkin = null;
            state.checkout = null;
            showDateMessage(
                "Vui lòng chọn loại phòng trước khi xem lịch trống.",
                true,
            );
        } else if (checkinBlocked || checkoutBlocked) {
            if (checkinBlocked) {
                state.checkin = null;
            }

            if (checkoutBlocked) {
                state.checkout = null;
            }

            showDateMessage(
                "Ngày đã qua hoặc kín lịch không thể chọn. Vui lòng chọn ngày màu xanh còn trống.",
                true,
            );
        } else if (
            state.checkin &&
            state.checkout &&
            state.checkout <= state.checkin
        ) {
            showDateMessage("Ngày trả phòng phải sau ngày nhận phòng.", true);
        } else if (
            state.checkin &&
            state.checkout &&
            rangeHasBlocked(state.checkin, state.checkout)
        ) {
            state.checkout = null;
            showDateMessage(
                "Khoảng thời gian này không còn phòng trống. Vui lòng chọn ngày khác.",
                true,
            );
        } else if (state.checkin && state.checkout) {
            showDateMessage(formatStaySummary(), false, true);
        } else {
            showDateMessage("Chọn hoặc nhập ngày nhận và ngày trả phòng.");
        }

        if (state.checkin) {
            state.calendarMonth = startOfMonth(state.checkin);
        }

        syncDateInputs();
        renderCalendar();
        updateStepVisibility();
        updateSummary();
    }

    function hasValidStay() {
        return (
            state.checkin &&
            state.checkout &&
            state.checkout > state.checkin &&
            !rangeHasBlocked(state.checkin, state.checkout)
        );
    }

    function joinMissingParts(parts) {
        if (parts.length <= 1) {
            return parts[0] || "";
        }

        if (parts.length === 2) {
            return `${parts[0]} và ${parts[1]}`;
        }

        return `${parts.slice(0, -1).join(", ")} và ${parts[parts.length - 1]}`;
    }

    function getSubmitBlockReason() {
        if (!isAuthenticated) {
            return "Vui lòng đăng nhập trước khi thanh toán.";
        }

        const missingParts = [];

        if (!state.room) {
            missingParts.push("loại phòng");
        }

        if (!hasValidStay()) {
            missingParts.push("ngày lưu trú");
        }

        if (state.selectedPets.size === 0) {
            missingParts.push("thú cưng");
        }

        if (missingParts.length === 0) {
            return "";
        }

        return `Vui lòng chọn ${joinMissingParts(missingParts)} trước khi thanh toán.`;
    }

    function updateSubmitState() {
        const blockReason = getSubmitBlockReason();
        const canSubmit = blockReason === "";

        // Xử lý nút Thanh toán (đã có)
        if (bookingSubmitBtn) {
            bookingSubmitBtn.disabled = !canSubmit;
            bookingSubmitBtn.setAttribute("aria-disabled", String(!canSubmit));
        }

        // THÊM MỚI: Xử lý nút Giữ chỗ
        if (bookingHoldBtn) {
            bookingHoldBtn.disabled = !canSubmit;
            bookingHoldBtn.setAttribute("aria-disabled", String(!canSubmit));
        }

        if (summaryActionHint) {
            summaryActionHint.textContent = canSubmit
                ? "Thông tin đã đủ, bạn có thể thanh toán hoặc giữ chỗ."
                : blockReason;
            summaryActionHint.classList.toggle("ready", canSubmit);
        }
    }

    function updateStepVisibility() {
        petPanel.hidden = !hasValidStay();
    }

    function getPetFromItem(item) {
        return {
            id: String(item.dataset.petId),
            name: item.dataset.petName,
            species: item.dataset.petSpecies,
            breed: item.dataset.petBreed,
            sex: item.dataset.petSex,
            weight: Number(item.dataset.petWeight || 0),
            isInRoom: item.dataset.petInRoom === "1",
            roomMessage:
                item.dataset.petRoomMessage ||
                "Thú cưng này đang ở trong phòng khác.",
        };
    }

    function checkPetEligibility(pet, alreadySelected) {
        if (pet.isInRoom) {
            return {
                ok: false,
                message: pet.roomMessage,
            };
        }

        if (!state.room) {
            return { ok: false, message: "Vui lòng chọn loại phòng trước." };
        }

        if (!alreadySelected && state.selectedPets.size >= state.room.maxPets) {
            return {
                ok: false,
                message: `Phòng ${state.room.name} tối đa ${state.room.maxPets} bé.`,
            };
        }

        if (state.room.maxWeight > 0 && pet.weight > state.room.maxWeight) {
            return {
                ok: false,
                message: `${pet.name} nặng ${pet.weight}kg, vượt mức ${state.room.maxWeight}kg của phòng ${state.room.name}.`,
            };
        }

        return {
            ok: true,
            message: `${pet.name} đạt điều kiện phòng ${state.room.name}.`,
        };
    }

    function togglePet(item, checked) {
        const checkbox = item.querySelector(".pet-checkbox");
        const status = item.querySelector(".pet-status");
        const serviceButton = item.querySelector(".pet-service-btn");
        const pet = getPetFromItem(item);

        if (!checked) {
            state.selectedPets.delete(pet.id);
            item.classList.remove("active", "ineligible");
            checkbox.checked = false;
            item.classList.toggle("ineligible", pet.isInRoom);
            status.textContent = pet.isInRoom
                ? pet.roomMessage
                : "Chọn bé để kiểm tra điều kiện phòng.";
            serviceButton.disabled = true;
            updateSummary();
            return;
        }

        const eligibility = checkPetEligibility(
            pet,
            state.selectedPets.has(pet.id),
        );

        if (!eligibility.ok) {
            state.selectedPets.delete(pet.id);
            item.classList.remove("active");
            item.classList.add("ineligible");
            checkbox.checked = false;
            status.textContent = eligibility.message;
            serviceButton.disabled = true;
            updateSummary();
            return;
        }

        state.selectedPets.set(pet.id, pet);
        item.classList.add("active");
        item.classList.remove("ineligible");
        checkbox.checked = true;
        status.textContent = eligibility.message;
        serviceButton.disabled = false;
        updateSummary();
    }

    function resetInvalidPets() {
        document.querySelectorAll(".pet-item").forEach((item) => {
            const pet = getPetFromItem(item);

            if (state.selectedPets.has(pet.id)) {
                togglePet(item, true);
            } else {
                item.classList.toggle("ineligible", pet.isInRoom);
                item.querySelector(".pet-status").textContent =
                    pet.isInRoom
                        ? pet.roomMessage
                        : "Chọn bé để kiểm tra điều kiện phòng.";
            }
        });
    }

    function setModalOpen(modal, isOpen) {
        if (!modal) {
            return;
        }

        modal.hidden = !isOpen;
        modal.setAttribute("aria-hidden", String(!isOpen));
        document.body.classList.toggle(
            "booking-modal-open",
            Boolean(document.querySelector(".booking-modal:not([hidden])")),
        );
    }

    function closeModals() {
        setModalOpen(serviceModal, false);
        state.activePetId = null;
    }

    function openServiceModal(petId) {
        const pet = state.selectedPets.get(String(petId));

        if (!pet) {
            return;
        }

        closeModals();
        state.activePetId = pet.id;
        servicePetName.textContent = pet.name;
        serviceModalList.innerHTML = "";

        const selectedServices = new Set(state.petServices[pet.id] || []);

        services.forEach((service) => {
            const label = document.createElement("label");
            label.className = "service-modal-item";
            label.innerHTML = `
        <span>
          <strong>${escapeHtml(service.name)}</strong>
          <span>${formatCurrency(service.price)}</span>
        </span>
        <input type="checkbox" class="service-modal-checkbox" value="${escapeHtml(service.id)}" ${selectedServices.has(String(service.id)) ? "checked" : ""}>
      `;
            serviceModalList.appendChild(label);
        });

        setModalOpen(serviceModal, true);
    }

    function savePetServices() {
        if (!state.activePetId) {
            return;
        }

        const selected = Array.from(
            serviceModalList.querySelectorAll(
                ".service-modal-checkbox:checked",
            ),
        ).map((checkbox) => String(checkbox.value));

        state.petServices[state.activePetId] = selected;
        const petItem = Array.from(document.querySelectorAll(".pet-item")).find(
            (item) => item.dataset.petId === state.activePetId,
        );

        if (petItem) {
            const button = petItem.querySelector(".pet-service-btn");
            button.textContent = selected.length
                ? `${selected.length} dịch vụ`
                : "+ Thêm dịch vụ";
        }

        closeModals();
        updateSummary();
    }

    function redirectToPetProfile() {
        const fallbackUrl = "/profile/pets/create";
        window.location.href = addPetBtn?.dataset.addPetUrl || fallbackUrl;
    }

    function updateSummary() {
        const nights = diffDays(state.checkin, state.checkout);
        const roomTotal = state.room && nights ? state.room.price * nights : 0;
        let serviceTotal = 0;

        document.getElementById("summaryRoomName").textContent = state.room
            ? state.room.name
            : "Chưa chọn";
        document.getElementById("summaryDates").textContent =
            state.checkin && state.checkout
                ? `${formatDateVN(state.checkin)} → ${formatDateVN(state.checkout)}`
                : "Chưa chọn";
        document.getElementById("summaryNights").textContent = `${nights} đêm`;
        document.getElementById("summaryRoomTotal").textContent =
            formatCurrency(roomTotal);

        const petList = document.getElementById("summaryPetList");
        const selectedPets = Array.from(state.selectedPets.values());

        if (selectedPets.length === 0) {
            petList.innerHTML =
                '<p class="summary-empty">Chưa chọn thú cưng</p>';
        } else {
            petList.innerHTML = selectedPets
                .map(
                    (pet) => `
        <div class="summary-line">
          <span>${escapeHtml(pet.name)}<small>${escapeHtml(pet.species)} · ${escapeHtml(pet.breed)} · ${escapeHtml(pet.weight)}kg</small></span>
        </div>
      `,
                )
                .join("");
        }

        const serviceList = document.getElementById("summaryServiceList");
        const serviceRows = [];

        selectedPets.forEach((pet) => {
            const ids = state.petServices[pet.id] || [];

            ids.forEach((serviceId) => {
                const service = servicesById.get(String(serviceId));

                if (!service) {
                    return;
                }

                serviceTotal += Number(service.price || 0);
                serviceRows.push(`
          <div class="summary-line">
            <span>${escapeHtml(service.name)}<small>${escapeHtml(pet.name)}</small></span>
            <strong>${formatCurrency(service.price)}</strong>
          </div>
        `);
            });
        });

        serviceList.innerHTML = serviceRows.length
            ? serviceRows.join("")
            : '<p class="summary-empty">Chưa chọn dịch vụ</p>';

        document.getElementById("summaryServiceTotal").textContent =
            formatCurrency(serviceTotal);
        document.getElementById("summaryGrandTotal").textContent =
            formatCurrency(roomTotal + serviceTotal);
        syncHiddenFields();
        updateSubmitState();
    }

    function syncHiddenFields() {
        const selectedPets = Array.from(state.selectedPets.keys());
        const serviceInputs = [];

        selectedPets.forEach((petId) => {
            (state.petServices[petId] || []).forEach((serviceId) => {
                serviceInputs.push(
                    `<input type="hidden" name="service_pet_ids[${escapeHtml(petId)}][]" value="${escapeHtml(serviceId)}">`,
                );
            });
        });

        hiddenFields.innerHTML = [
            ...selectedPets.map(
                (petId) =>
                    `<input type="hidden" name="pet_ids[]" value="${escapeHtml(petId)}">`,
            ),
            ...serviceInputs,
        ].join("");
    }

    function showAlert(message) {
        bookingAlert.textContent = message;
        bookingAlert.hidden = false;
        bookingAlert.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function validateBeforeSubmit(event) {
        const blockReason = getSubmitBlockReason();

        if (blockReason) {
            event.preventDefault();
            showAlert(blockReason);
            updateSubmitState();
            return;
        }

        if (!state.room) {
            event.preventDefault();
            showAlert("Vui lòng chọn loại phòng.");
            return;
        }

        if (!hasValidStay()) {
            event.preventDefault();
            showAlert("Vui lòng chọn ngày lưu trú hợp lệ.");
            return;
        }

        if (state.selectedPets.size === 0) {
            event.preventDefault();
            showAlert("Vui lòng chọn ít nhất 1 thú cưng đạt điều kiện phòng.");
        }
        [bookingSubmitBtn, bookingHoldBtn].forEach((button) => {
            if (!button) {
                return;
            }

            button.disabled = true;
            button.setAttribute("aria-disabled", "true");
        });
    }

    function setBookingAction(action) {
        if (bookingActionInput) {
            bookingActionInput.value = action;
        }
    }

    function submitBookingWithAction(action) {
        setBookingAction(action);

        const blockReason = getSubmitBlockReason();

        if (blockReason) {
            showAlert(blockReason);
            updateSubmitState();
            setBookingAction("pay");
            return;
        }

        if (typeof bookingForm.requestSubmit === "function") {
            bookingForm.requestSubmit();
            return;
        }

        bookingForm.submit();
    }

    roomCards.forEach((card) => {
        card.addEventListener("click", function () {
            selectRoom(card);
        });
    });

    checkinInput.addEventListener("change", handleManualDateChange);
    checkoutInput.addEventListener("change", handleManualDateChange);

    prevMonthBtn?.addEventListener("click", function () {
        if (prevMonthBtn.disabled) {
            return;
        }

        state.calendarMonth = addMonths(state.calendarMonth || today, -1);
        renderCalendar();
    });

    nextMonthBtn?.addEventListener("click", function () {
        state.calendarMonth = addMonths(state.calendarMonth || today, 1);
        renderCalendar();
    });

    petSelectList.addEventListener("change", function (event) {
        if (!event.target.classList.contains("pet-checkbox")) {
            return;
        }

        togglePet(event.target.closest(".pet-item"), event.target.checked);
    });

    petSelectList.addEventListener("click", function (event) {
        const button = event.target.closest(".pet-service-btn");

        if (!button || button.disabled) {
            return;
        }

        openServiceModal(button.closest(".pet-item").dataset.petId);
    });

    addPetBtn.addEventListener("click", redirectToPetProfile);

    saveServiceBtn.addEventListener("click", savePetServices);
    bookingSubmitBtn?.addEventListener("click", function () {
        setBookingAction("pay");
    });
    bookingHoldBtn?.addEventListener("click", function (event) {
        event.preventDefault();
        submitBookingWithAction("hold");
    });
    bookingForm.addEventListener("submit", validateBeforeSubmit);

    document.querySelectorAll("[data-close-modal]").forEach((button) => {
        button.addEventListener("click", closeModals);
    });

    document.querySelectorAll(".booking-modal").forEach((modal) => {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                closeModals();
            }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModals();
        }
    });

    closeModals();
    syncDateInputs();
    updateSummary();
});
