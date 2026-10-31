// assets/js/booking.js - Xử lý tính toán và sinh form danh sách hành khách đi đoàn
document.addEventListener('DOMContentLoaded', function () {
    const numAdultsInput = document.getElementById('num_adults');
    const numChildrenInput = document.getElementById('num_children');
    const numInfantsInput = document.getElementById('num_infants');
    const passengerList = document.getElementById('passenger_list');

    const priceAdult = parseInt(document.getElementById('price_adult_val')?.value || 0);
    const priceChild = parseInt(document.getElementById('price_child_val')?.value || 0);
    const priceInfant = parseInt(document.getElementById('price_infant_val')?.value || 0);
    const priceSingleRoom = parseInt(document.getElementById('price_single_val')?.value || 0);

    const subtotalDisplay = document.getElementById('display_subtotal');
    const singleTotalDisplay = document.getElementById('display_single_total');
    const totalDisplay = document.getElementById('display_total');
    const depositDisplay = document.getElementById('display_deposit');

    // Hàm cập nhật danh sách nhập thông tin từng hành khách bảo hiểm
    function renderPassengerRows() {
        const adults = Math.max(1, parseInt(numAdultsInput.value) || 1);
        const children = Math.max(0, parseInt(numChildrenInput.value) || 0);
        const infants = Math.max(0, parseInt(numInfantsInput.value) || 0);

        let html = '';
        let index = 1;

        // Hành khách Người lớn
        for (let i = 1; i <= adults; i++) {
            html += `
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span class="flex items-center gap-1.5 text-blue-600">
                        <i data-lucide="user" class="w-3.5 h-3.5"></i> Khách #${index} (Người lớn ${i})
                    </span>
                    <label class="flex items-center gap-1.5 text-[11px] font-medium text-slate-600 cursor-pointer">
                        <input type="checkbox" name="passenger_single_room[${index - 1}]" value="1" class="single-room-cb rounded text-blue-600">
                        Phòng đơn (+${new Intl.NumberFormat('vi-VN').format(priceSingleRoom)} đ)
                    </label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
                    <input type="hidden" name="passenger_type[]" value="Người lớn">
                    <input type="text" name="passenger_name[]" required placeholder="Họ và tên (*)" class="border border-slate-300 rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
                    <select name="passenger_gender[]" class="border border-slate-300 rounded-lg p-2">
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                    <input type="date" name="passenger_birth[]" class="border border-slate-300 rounded-lg p-2" title="Ngày sinh">
                    <input type="text" name="passenger_idcard[]" required placeholder="Số CCCD/Hộ chiếu (*)" class="border border-slate-300 rounded-lg p-2 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>`;
            index++;
        }

        // Hành khách Trẻ em (5 - 11 tuổi)
        for (let i = 1; i <= children; i++) {
            html += `
            <div class="p-3.5 bg-amber-50/50 border border-amber-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-amber-700">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="smile" class="w-3.5 h-3.5"></i> Khách #${index} (Trẻ em 5-11 tuổi ${i})
                    </span>
                    <span class="text-[10px] text-amber-600 font-normal">Khai sinh/CCCD trẻ em</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
                    <input type="hidden" name="passenger_type[]" value="Trẻ em">
                    <input type="hidden" name="passenger_single_room[${index - 1}]" value="0">
                    <input type="text" name="passenger_name[]" required placeholder="Họ và tên trẻ em (*)" class="border border-slate-300 rounded-lg p-2">
                    <select name="passenger_gender[]" class="border border-slate-300 rounded-lg p-2">
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                    </select>
                    <input type="date" name="passenger_birth[]" class="border border-slate-300 rounded-lg p-2" required title="Ngày sinh để xác nhận độ tuổi">
                    <input type="text" name="passenger_idcard[]" placeholder="Số định danh / Giấy khai sinh" class="border border-slate-300 rounded-lg p-2">
                </div>
            </div>`;
            index++;
        }

        // Hành khách Em bé (Dưới 5 tuổi)
        for (let i = 1; i <= infants; i++) {
            html += `
            <div class="p-3.5 bg-purple-50/50 border border-purple-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-purple-700">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="baby" class="w-3.5 h-3.5"></i> Khách #${index} (Em bé dưới 5 tuổi ${i})
                    </span>
                    <span class="text-[10px] text-purple-600 font-normal">Bảo hiểm du lịch theo đoàn</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
                    <input type="hidden" name="passenger_type[]" value="Em bé">
                    <input type="hidden" name="passenger_single_room[${index - 1}]" value="0">
                    <input type="text" name="passenger_name[]" required placeholder="Họ và tên em bé (*)" class="border border-slate-300 rounded-lg p-2">
                    <select name="passenger_gender[]" class="border border-slate-300 rounded-lg p-2">
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                    </select>
                    <input type="date" name="passenger_birth[]" class="border border-slate-300 rounded-lg p-2" required title="Ngày sinh em bé">
                    <input type="text" name="passenger_idcard[]" placeholder="Giấy khai sinh / Đăng ký hộ khẩu" class="border border-slate-300 rounded-lg p-2">
                </div>
            </div>`;
            index++;
        }

        passengerList.innerHTML = html;
        if (window.lucide) {
            lucide.createIcons();
        }

        // Gán sự kiện cho các checkbox phòng đơn
        document.querySelectorAll('.single-room-cb').forEach(cb => {
            cb.addEventListener('change', calculateSummary);
        });

        calculateSummary();
    }

    // Hàm tính tổng chi phí ước tính trên giao diện
    function calculateSummary() {
        const adults = Math.max(1, parseInt(numAdultsInput.value) || 1);
        const children = Math.max(0, parseInt(numChildrenInput.value) || 0);
        const infants = Math.max(0, parseInt(numInfantsInput.value) || 0);

        const checkedRooms = document.querySelectorAll('.single-room-cb:checked').length;
        const singleTotal = checkedRooms * priceSingleRoom;

        const subtotal = (adults * priceAdult) + (children * priceChild) + (infants * priceInfant);
        const grandTotal = subtotal + singleTotal;
        const depositAmount = Math.round(grandTotal * 0.5);

        if (subtotalDisplay) subtotalDisplay.innerText = new Intl.NumberFormat('vi-VN').format(subtotal) + ' đ';
        if (singleTotalDisplay) singleTotalDisplay.innerText = new Intl.NumberFormat('vi-VN').format(singleTotal) + ' đ';
        if (totalDisplay) totalDisplay.innerText = new Intl.NumberFormat('vi-VN').format(grandTotal) + ' đ';
        if (depositDisplay) depositDisplay.innerText = new Intl.NumberFormat('vi-VN').format(depositAmount) + ' đ';
    }

    // Gắn sự kiện thay đổi số lượng vé
    if (numAdultsInput) numAdultsInput.addEventListener('input', renderPassengerRows);
    if (numChildrenInput) numChildrenInput.addEventListener('input', renderPassengerRows);
    if (numInfantsInput) numInfantsInput.addEventListener('input', renderPassengerRows);

    // Render ban đầu khi tải trang
    if (passengerList) {
        renderPassengerRows();
    }
});