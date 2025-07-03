<?php
// FILE: /app/controllers/admin/ProductController.php
// MÔ TẢ: Controller quản lý Sản phẩm.

namespace Admin;

class ProductController extends AdminBaseController
{

    // Hiển thị trang danh sách sản phẩm
    public function index(): void
    {
        // Lấy giá trị bộ lọc từ URL
        $filter_name = trim($_GET['product_name'] ?? '');
        $filter_category = (int)($_GET['category'] ?? 0);
        $filter_brand = (int)($_GET['brand'] ?? 0);
        $filter_identifier = trim($_GET['identifier'] ?? '');

        // Xây dựng câu truy vấn SQL
        $sql = "SELECT
                    sp.id, sp.ten_san_pham, sp.gia_goc, sp.is_active, sp.loai_san_pham,
                    dm.ten_danh_muc, th.ten_thuong_hieu,
                    (SELECT ha.url_hinh_anh FROM hinh_anh_san_pham ha WHERE ha.san_pham_id = sp.id AND ha.la_anh_dai_dien = 1 LIMIT 1) as url_hinh_anh,
                    (SELECT MIN(pv.gia) FROM product_variants pv WHERE pv.san_pham_id = sp.id AND pv.gia > 0) as min_variant_price,
                    (SELECT MAX(pv.gia) FROM product_variants pv WHERE pv.san_pham_id = sp.id) as max_variant_price
                FROM san_pham sp
                LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id
                LEFT JOIN thuong_hieu th ON sp.thuong_hieu_id = th.id
                WHERE 1=1";
        $params = [];

        if (!empty($filter_name)) {
            $sql .= " AND sp.ten_san_pham LIKE ?";
            $params[] = '%' . $filter_name . '%';
        }
        if ($filter_category > 0) {
            $sql .= " AND sp.danh_muc_id = ?";
            $params[] = $filter_category;
        }
        if ($filter_brand > 0) {
            $sql .= " AND sp.thuong_hieu_id = ?";
            $params[] = $filter_brand;
        }
        if (!empty($filter_identifier)) {
            $sql .= " AND (
                        sp.ma_dinh_danh_duy_nhat = ? 
                        OR EXISTS (
                            SELECT 1 FROM product_variants pv 
                            WHERE pv.san_pham_id = sp.id 
                            AND (pv.ma_bien_the = ? OR pv.unique_identifiers LIKE ?)
                        )
                    )";
            $params[] = $filter_identifier;
            $params[] = $filter_identifier;
            $params[] = '%' . $filter_identifier . '%';
        }

        $sql .= " GROUP BY sp.id ORDER BY sp.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        // Dữ liệu cho bộ lọc
        $all_categories = $this->pdo->query("SELECT id, ten_danh_muc FROM danh_muc ORDER BY ten_danh_muc")->fetchAll();
        $all_brands = $this->pdo->query("SELECT id, ten_thuong_hieu FROM thuong_hieu ORDER BY ten_thuong_hieu")->fetchAll();
        $category_brands_map_raw = $this->pdo->query("SELECT danh_muc_id, GROUP_CONCAT(thuong_hieu_id) as brand_ids FROM danhmuc_thuonghieu GROUP BY danh_muc_id")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $category_brands_map = [];
        foreach ($category_brands_map_raw as $key => $value) {
            $category_brands_map[$key] = array_map('intval', explode(',', $value));
        }

        $data = [
            'page_title' => 'Quản lý Sản phẩm',
            'products' => $products,
            'all_categories' => $all_categories,
            'all_brands' => $all_brands,
            'category_brands_map' => $category_brands_map,
            'filter_name' => $filter_name,
            'filter_category' => $filter_category,
            'filter_brand' => $filter_brand,
            'filter_identifier' => $filter_identifier,
        ];
        $this->render('pages/products', $data);
    }

    // Hiển thị form thêm/sửa
    private function showProductForm(int $id = 0): void
    {
        $is_edit = $id > 0;
        $product = $images = $product_attributes = $variants = [];
        $stock_quantity = 0;
        $selected_variant_attributes = [];

        if ($is_edit) {
            $stmt = $this->pdo->prepare("SELECT sp.*, th.ten_thuong_hieu FROM san_pham sp LEFT JOIN thuong_hieu th ON sp.thuong_hieu_id = th.id WHERE sp.id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) {
                $_SESSION['message'] = "Sản phẩm không tồn tại.";
                $_SESSION['message_type'] = "danger";
                header('Location: ' . \BASE_URL . '/admin/products');
                exit;
            }

            $images = $this->pdo->query("SELECT id, url_hinh_anh, la_anh_dai_dien FROM hinh_anh_san_pham WHERE san_pham_id = $id ORDER BY la_anh_dai_dien DESC, id ASC")->fetchAll(\PDO::FETCH_ASSOC);
            $product_attributes = $this->pdo->query("SELECT thuoc_tinh_id, gia_tri FROM gia_tri_thuoc_tinh WHERE san_pham_id = $id")->fetchAll(\PDO::FETCH_KEY_PAIR);

            if ($product['loai_san_pham'] === 'simple') {
                $stock_quantity = $product['so_luong_ton'];
            } else {
                // Lấy các biến thể và thuộc tính đã chọn cho biến thể
            }
        }

        // Lấy tất cả dữ liệu cần thiết cho form
        $all_categories = $this->pdo->query("SELECT id, ten_danh_muc FROM danh_muc ORDER BY vi_tri")->fetchAll(\PDO::FETCH_ASSOC);
        // ... (lấy các dữ liệu khác như brands, attributes, maps...)

        $data = [
            'page_title' => $is_edit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới',
            'is_edit' => $is_edit,
            'product' => $product,
            'images' => $images,
            'product_attributes' => $product_attributes,
            'variants' => $variants,
            'stock_quantity' => $stock_quantity,
            'selected_variant_attributes' => $selected_variant_attributes,
            'all_categories' => $all_categories,
            // ... (truyền tất cả dữ liệu khác vào đây)
        ];

        $this->render('pages/product_edit', $data);
    }

    public function add(): void
    {
        $this->showProductForm(0);
    }

    public function edit(int $id): void
    {
        $this->showProductForm($id);
    }
}
