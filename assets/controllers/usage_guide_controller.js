import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.driverFactory = window.driver?.js?.driver;
        this.guide = new URLSearchParams(window.location.search).get('guide');

        if (!this.driverFactory || !this.guide) {
            return;
        }

        window.requestAnimationFrame(() => this.startGuide(this.guide));
    }

    start(event) {
        const guide = event.currentTarget.dataset.guideStart;
        if (!guide) {
            return;
        }

        const target = event.currentTarget.dataset.guideTarget;
        if (target) {
            window.location.href = `${target}${target.includes('?') ? '&' : '?'}guide=${encodeURIComponent(guide)}`;
        }
    }

    startGuide(name) {
        const definitions = this.definitions();
        const definition = definitions[name];
        if (!definition) {
            return;
        }

        const steps = definition.steps
            .filter((step) => !step.element || document.querySelector(step.element))
            .map((step) => ({
                ...step,
                popover: {
                    ...step.popover,
                    className: 'mobile-pos-driver-popover',
                },
            }));

        if (!steps.length) {
            return;
        }

        const driverObj = this.driverFactory({
            showProgress: true,
            progressText: '{{current}} / {{total}}',
            nextBtnText: 'Tiếp',
            prevBtnText: 'Quay lại',
            doneBtnText: 'Hoàn tất',
            allowClose: true,
            smoothScroll: true,
            allowScroll: true,
            overlayOpacity: 0.58,
            popoverClass: 'mobile-pos-driver-popover',
            steps,
            onCloseClick: () => driverObj.destroy(),
            onDoneClick: () => driverObj.destroy(),
        });

        driverObj.drive();
    }

    definitions() {
        return {
            pos: {
                steps: [
                    { element: '[data-guide="pos-product-search"]', popover: { title: '1. Tìm sản phẩm', description: 'Nhập tên hoặc SKU để tìm sản phẩm. Sau đó chọn sản phẩm để thêm vào giỏ.' } },
                    { element: '[data-guide="pos-cart"]', popover: { title: '2. Kiểm tra giỏ hàng', description: 'Kiểm tra sản phẩm, số lượng và tổng tiền trước khi thanh toán.' } },
                    { element: '[data-guide="pos-customer-search"]', popover: { title: '3. Chọn khách hàng', description: 'Nếu cần gắn giao dịch cho khách hàng hoặc tạo công nợ, chọn khách hàng ở đây.' } },
                    { element: '[data-guide="pos-payment-method"]', popover: { title: '4. Chọn phương thức thanh toán', description: 'Chọn tiền mặt hoặc chuyển khoản. Hệ thống sẽ hiển thị đúng phần nhập tiền tương ứng.' } },
                    { element: '[data-guide="pos-checkout"]', popover: { title: '5. Hoàn tất bán hàng', description: 'Kiểm tra lần cuối rồi bấm Hoàn tất bán hàng. Hướng dẫn không tự thực hiện giao dịch thay bạn.' } },
                ],
            },
            orders: {
                steps: [
                    { element: '[data-guide="orders-filters"]', popover: { title: '1. Tìm và lọc đơn hàng', description: 'Nhập mã đơn, khách hàng hoặc tiêu chí phù hợp rồi chọn trạng thái nếu cần.' } },
                    { element: '[data-guide="orders-search"]', popover: { title: '2. Nhập nội dung tìm kiếm', description: 'Tập trung vào ô này để tìm nhanh đơn hàng.' } },
                    { element: '[data-guide="orders-apply"]', popover: { title: '3. Áp dụng bộ lọc', description: 'Bấm tìm kiếm để tải danh sách đơn hàng theo điều kiện đã chọn.' } },
                ],
            },
            customers: {
                steps: [
                    { element: '[data-guide="customers-search"]', popover: { title: '1. Tìm khách hàng', description: 'Tìm theo tên hoặc số điện thoại.' } },
                    { element: '[data-guide="customers-create"]', popover: { title: '2. Tạo khách hàng', description: 'Nếu chưa có khách hàng, mở form tạo mới tại đây.' } },
                ],
            },
            debt: {
                steps: [
                    { element: '[data-guide="debt-search"]', popover: { title: '1. Tìm công nợ', description: 'Tìm theo khách hàng hoặc lọc theo trạng thái công nợ.' } },
                ],
            },
            products: {
                steps: [
                    { element: '[data-guide="products-toolbar"]', popover: { title: '1. Quản lý sản phẩm', description: 'Từ khu vực này bạn có thể tạo sản phẩm hoặc mở các công cụ import khi có quyền.' } },
                    { element: '[data-guide="products-filter"]', popover: { title: '2. Tìm và lọc', description: 'Tìm theo tên/SKU và lọc theo danh mục trước khi chọn sản phẩm.' } },
                    { element: '[data-guide="products-search"]', popover: { title: '3. Tìm sản phẩm', description: 'Nhập tên hoặc SKU để thu hẹp danh sách.' } },
                ],
            },
            stock: {
                steps: [
                    { element: '[data-guide="stock-workspace"]', popover: { title: '1. Màn hình tồn kho', description: 'Đây là nơi theo dõi số lượng tồn và các cảnh báo tồn kho.' } },
                    { element: '[data-guide="stock-search"]', popover: { title: '2. Tìm sản phẩm trong kho', description: 'Tìm theo tên hoặc SKU để kiểm tra tồn kho nhanh hơn.' } },
                ],
            },
            statistics: {
                steps: [
                    { element: '[data-guide="statistics-filter"]', popover: { title: '1. Chọn phạm vi báo cáo', description: 'Chọn kỳ báo cáo hoặc nhập khoảng ngày cần xem.' } },
                    { element: '[data-guide="statistics-preset"]', popover: { title: '2. Chọn nhanh khoảng thời gian', description: 'Có thể chọn hôm nay, hôm qua, tuần hoặc tháng.' } },
                    { element: '[data-guide="statistics-apply"]', popover: { title: '3. Cập nhật báo cáo', description: 'Bấm Áp dụng để tải lại số liệu theo bộ lọc.' } },
                ],
            },
            settings: {
                steps: [
                    { element: '[data-guide="settings-form"]', popover: { title: '1. Cài đặt cá nhân', description: 'Các thay đổi về giao diện và trợ năng được lưu cho tài khoản hiện tại.' } },
                    { element: '[data-guide="settings-payment-sound"]', popover: { title: '2. Âm thanh thanh toán', description: 'Bật hoặc tắt âm thanh báo hiệu khi thanh toán hoàn tất. Mặc định hiện tại là bật.' } },
                    { element: '[data-guide="settings-usage-guide"]', popover: { title: '3. Hướng dẫn sử dụng', description: 'Bạn có thể tắt phần hướng dẫn trên trang chủ nhưng vẫn mở lại hướng dẫn thủ công.' } },
                    { element: '[data-guide="settings-save"]', popover: { title: '4. Lưu thay đổi', description: 'Bấm Lưu cài đặt để áp dụng các lựa chọn.' } },
                ],
            },
        };
    }
}
