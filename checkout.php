<?php
session_start();

// Kiểm tra xem người dùng đã bấm nút Đặt hàng chưa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['cart'])) {
    // Kiểm tra các trường thông tin người đặt hàng
    if (isset($_POST['name'], $_POST['address'], $_POST['phone'])) {
        // Lấy dữ liệu từ biểu mẫu và kiểm tra an toàn
        $name = htmlspecialchars(trim($_POST['name']));
        $address = htmlspecialchars(trim($_POST['address']));
        $phone = htmlspecialchars(trim($_POST['phone']));

        // Kiểm tra dữ liệu đầu vào
        if (strlen($name) > 100 || strlen($address) > 255 || !preg_match('/^[0-9]{10,15}$/', $phone)) {
            echo "Dữ liệu nhập không hợp lệ.";
            exit();
        }

        // Kết nối CSDL
        include_once __DIR__ . "/dbconnect.php";

        // Sử dụng prepared statement để bảo vệ chống SQL Injection
        $stmt = $conn->prepare("INSERT INTO orders (order_date, total_amount, status, name, address, phone) VALUES (NOW(), 0, 'Đang xử lý', ?, ?, ?)");
        $stmt->bind_param("sss", $name, $address, $phone);

        if ($stmt->execute()) {
            // Lấy order_id mới được tạo
            $order_id = $stmt->insert_id;

            // Lặp qua các sản phẩm trong giỏ hàng
            $stmt_product = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
            $stmt_order_details = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");

            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                // Kiểm tra dữ liệu đầu vào của product_id và quantity
                if (!is_numeric($product_id) || !is_numeric($quantity) || $quantity <= 0) {
                    continue;
                }

                $stmt_product->bind_param("i", $product_id);
                $stmt_product->execute();
                $result = $stmt_product->get_result();

                if ($result && $row = $result->fetch_assoc()) {
                    $price = floatval($row['price']);
                    $subtotal = $price * $quantity;

                    // Chèn vào bảng order_details
                    $stmt_order_details->bind_param("iiid", $order_id, $product_id, $quantity, $price);
                    $stmt_order_details->execute();
                }
            }

            // Cập nhật tổng số tiền cho đơn hàng
            $stmt_update = $conn->prepare("UPDATE orders SET total_amount = (SELECT SUM(price * quantity) FROM order_details WHERE order_id = ?) WHERE order_id = ?");
            $stmt_update->bind_param("ii", $order_id, $order_id);
            $stmt_update->execute();

            // Xóa thông tin giỏ hàng sau khi đặt hàng thành công
            unset($_SESSION['cart']);

            // Chuyển hướng về trang giỏ hàng với thông báo đặt hàng thành công
            header("Location: cart.php?order_success=true");
            exit();
        } else {
            echo "Đặt hàng không thành công. Vui lòng thử lại.";
        }

        // Đóng kết nối
        $stmt->close();
        $conn->close();
    } else {
        echo "Vui lòng nhập đầy đủ thông tin người đặt hàng.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="images/favicon.png">
    <title>Đặt hàng</title>
    <?php include_once __DIR__ . "/styles.php"; ?>
</head>
<body>
    <div class="wrapper">
        <?php include_once __DIR__ . "/fontend/layouts/header.php"; ?>
        <div class="container_fullwidth">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <h1>Đặt hàng</h1>
                        <form action="checkout.php" method="post">
                            <div class="form-group">
                                <label for="name">Họ và tên:</label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Địa chỉ:</label>
                                <input type="text" name="address" id="address" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Số điện thoại:</label>
                                <input type="text" name="phone" id="phone" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Đặt hàng</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include_once __DIR__ . "/fontend/layouts/footer.php"; ?>
    </div>
    <?php include_once __DIR__ . "/scripts.php"; ?>
</body>
</html>
