<?php
// FILE: /app/controllers/admin/AttributeController.php
// MÔ TẢ: Controller quản lý Thuộc tính Biến thể, đã được tích hợp đầy đủ các hành động.

namespace Admin;

class AttributeController extends AdminBaseController
{

    /**
     * Hiển thị trang quản lý thuộc tính chính.
     */
    public function index(): void
    {
        $attributes = $this->pdo->query("
            SELECT tt.id, tt.ten_thuoc_tinh, GROUP_CONCAT(gtt.id, '::', gtt.gia_tri SEPARATOR '||') as `values`
            FROM thuoc_tinh_bien_the tt
            LEFT JOIN gia_tri_thuoc_tinh_bien_the gtt ON tt.id = gtt.thuoc_tinh_id
            GROUP BY tt.id
            ORDER BY tt.ten_thuoc_tinh
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'page_title' => 'Quản lý Thuộc tính Biến thể',
            'attributes_json' => json_encode($attributes, JSON_NUMERIC_CHECK),
        ];

        $this->render('pages/attributes', $data);
    }

    /**
     * Xử lý AJAX để thêm một thuộc tính mới.
     */
    public function add()
    {
        try {
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                throw new \Exception("Tên thuộc tính không được để trống.");
            }
            $stmt = $this->pdo->prepare("INSERT INTO thuoc_tinh_bien_the (ten_thuoc_tinh) VALUES (?)");
            $stmt->execute([$name]);
            $this->jsonResponse(true, 'Thêm thành công', [
                'id' => $this->pdo->lastInsertId(),
                'name' => $name
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Xử lý AJAX để cập nhật tên một thuộc tính.
     */
    public function update()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (empty($name) || $id === 0) {
                throw new \Exception("Dữ liệu không hợp lệ.");
            }
            $this->pdo->prepare("UPDATE thuoc_tinh_bien_the SET ten_thuoc_tinh = ? WHERE id = ?")->execute([$name, $id]);
            $this->jsonResponse(true, 'Cập nhật thành công.');
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Xử lý AJAX để xóa một thuộc tính (và các giá trị con của nó).
     */
    public function delete()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === 0) {
                throw new \Exception("ID không hợp lệ.");
            }
            // Trong thực tế, bạn nên kiểm tra xem thuộc tính có đang được sử dụng không trước khi xóa.
            $this->pdo->prepare("DELETE FROM thuoc_tinh_bien_the WHERE id = ?")->execute([$id]);
            // Các giá trị con sẽ tự động bị xóa nếu có ràng buộc khóa ngoại (ON DELETE CASCADE)
            $this->jsonResponse(true, 'Xóa thành công.');
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Xử lý AJAX để thêm một giá trị mới cho thuộc tính.
     */
    public function addValue()
    {
        try {
            $attr_id = (int)($_POST['attribute_id'] ?? 0);
            $value = trim($_POST['value'] ?? '');
            if (empty($value) || $attr_id === 0) {
                throw new \Exception("Dữ liệu không hợp lệ.");
            }
            $stmt = $this->pdo->prepare("INSERT INTO gia_tri_thuoc_tinh_bien_the (thuoc_tinh_id, gia_tri) VALUES (?, ?)");
            $stmt->execute([$attr_id, $value]);
            $this->jsonResponse(true, 'Thêm giá trị thành công', [
                'id' => $this->pdo->lastInsertId(),
                'value' => $value
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Xử lý AJAX để cập nhật một giá trị của thuộc tính.
     */
    public function updateValue()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $value = trim($_POST['value'] ?? '');
            if (empty($value) || $id === 0) {
                throw new \Exception("Dữ liệu không hợp lệ.");
            }
            $this->pdo->prepare("UPDATE gia_tri_thuoc_tinh_bien_the SET gia_tri = ? WHERE id = ?")->execute([$value, $id]);
            $this->jsonResponse(true, 'Cập nhật giá trị thành công.');
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Xử lý AJAX để xóa một giá trị của thuộc tính.
     */
    public function deleteValue()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === 0) {
                throw new \Exception("ID không hợp lệ.");
            }
            // Tương tự, bạn nên kiểm tra xem giá trị này có đang được dùng trong biến thể nào không.
            $this->pdo->prepare("DELETE FROM gia_tri_thuoc_tinh_bien_the WHERE id = ?")->execute([$id]);
            $this->jsonResponse(true, 'Xóa giá trị thành công.');
        } catch (\Exception $e) {
            $this->jsonResponse(false, $e->getMessage());
        }
    }
}
