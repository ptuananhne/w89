<?php
// FILE: /app/controllers/admin/AjaxController.php
// MÔ TẢ: Controller chuyên xử lý tất cả các yêu cầu AJAX từ trang admin.

namespace Admin;

class AjaxController extends AdminBaseController
{

    // Hàm xử lý chính, gọi đến các hàm con dựa trên 'action'
    public function handle(string $action): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, 'Phương thức không hợp lệ.');
        }

        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(false, 'Lỗi xác thực CSRF!', ['http_code' => 403]);
        }

        try {
            $this->pdo->beginTransaction();

            switch ($action) {
                case 'save_product':
                    $this->saveProduct();
                    break;
                case 'delete_image':
                    $this->deleteImage();
                    break;
                case 'update_category_order':
                    $this->updateCategoryOrder();
                    break;
                // ... (Thêm các case khác cho các action AJAX từ file cũ)
                default:
                    $this->jsonResponse(false, 'Hành động không xác định.');
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("AJAX Error: " . $e->getMessage());
            $this->jsonResponse(false, 'Đã xảy ra lỗi ở phía máy chủ.', ['http_code' => 500]);
        }
    }

    // Chuyển toàn bộ logic từ file ajax_handler.php cũ vào đây
    private function saveProduct()
    {
        // ... (Toàn bộ logic của case 'save_product' trong ajax_handler.php)
        // Ví dụ:
        $id = (int)($_POST['id'] ?? 0);
        // ...

        $this->jsonResponse(true, 'Lưu sản phẩm thành công!', ['redirect_url' => \BASE_URL . '/admin/products/edit/' . $id]);
    }

    private function deleteImage()
    {
        // ... (Logic của case 'delete_image' trong ajax_handler.php)
        $this->jsonResponse(true, 'Xóa ảnh thành công.');
    }

    private function updateCategoryOrder()
    {
        $order = json_decode(file_get_contents('php://input'), true)['order'] ?? [];
        if (!empty($order)) {
            $stmt = $this->pdo->prepare("UPDATE danh_muc SET vi_tri = ? WHERE id = ?");
            foreach ($order as $position => $id) {
                $stmt->execute([$position, (int)$id]);
            }
        }
        $this->jsonResponse(true, 'Cập nhật thứ tự thành công.');
    }
}
