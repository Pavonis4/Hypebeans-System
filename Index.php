<?php
session_start();
$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] === 'admin';

// Database connection details (replace with your actual credentials)
$servername = "localhost";
$username = "root"; // Your database username
$password = "";      // Your database password
$dbname = "HyBeans"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize orders session
if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}

// Function to record a sale in the database
function recordSale($total, $items, $conn) {
    // Set the PHP timezone to Asia/Manila (or Asia/Taipei)
    date_default_timezone_set('Asia/Manila'); // Or date_default_timezone_set('Asia/Taipei');
    $timestamp = date('Y-m-d H:i:s');

    // 1. Insert into the 'sales' table
    $sql_sales = "INSERT INTO sales (sale_timestamp, total_amount) VALUES (?, ?)";
    $stmt_sales = $conn->prepare($sql_sales);
    $stmt_sales->bind_param("sd", $timestamp, $total);

    if ($stmt_sales->execute()) {
        $sale_id = $conn->insert_id; // Get the ID of the newly inserted sale

        // 2. Insert into the 'sale_items' table for each item in the order
        // Modified to include 'temperature'
        $sql_items = "INSERT INTO sale_items (sale_id, item_name, item_price, quantity, temperature) VALUES (?, ?, ?, 1, ?)";
        $stmt_items = $conn->prepare($sql_items);

        foreach ($items as $item) {
            $temperature = isset($item['temperature']) ? $item['temperature'] : null;
            $stmt_items->bind_param("isds", $sale_id, $item['item'], $item['price'], $temperature);
            $stmt_items->execute();
        }

        $stmt_items->close();
        $stmt_sales->close();
        return true; // Sale recorded successfully
    } else {
        $stmt_sales->close();
        return false; // Failed to record sale
    }
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $item = $_POST['item'];
        $price = $_POST['price'];
        $temperature = isset($_POST['temperature']) ? $_POST['temperature'] : null; // Get temperature if sent
        $_SESSION['orders'][] = [
            'item' => $item,
            'price' => $price,
            'temperature' => $temperature, // Store temperature
        ];
        echo json_encode(['success' => true, 'orders' => $_SESSION['orders']]);
        exit;
    } elseif ($action === 'edit') {
        $index = $_POST['index'];
        $new_item = $_POST['new_item'];
        $new_price = $_POST['new_price'];
        $new_temperature = isset($_POST['new_temperature']) ? $_POST['new_temperature'] : null; // Get new temperature
        if (isset($_SESSION['orders'][$index])) {
            $_SESSION['orders'][$index]['item'] = $new_item;
            $_SESSION['orders'][$index]['price'] = $new_price;
            $_SESSION['orders'][$index]['temperature'] = $new_temperature; // Update temperature
        }
        echo json_encode(['success' => true, 'orders' => $_SESSION['orders']]);
        exit;
    } elseif ($action === 'delete') {
        $index = $_POST['index'];
        if (isset($_SESSION['orders'][$index])) {
            array_splice($_SESSION['orders'], $index, 1);
        }
        echo json_encode(['success' => true, 'orders' => $_SESSION['orders']]);
        exit;
    } elseif ($action === 'clear') {
        $_SESSION['orders'] = [];
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'record_sale') {
        $total = $_POST['total'];
        $items = $_SESSION['orders']; // Get the current order items

        // Call the recordSale function to save to the database
        if (recordSale($total, $items, $conn)) {
            $_SESSION['orders'] = []; // Clear the cart after successful sale
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
}

// Close the database connection at the end of the script
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hypebeans Coffee Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9Oer-E4K+OeYdHR9xOofG/I12r3FqXvGg1uK0zI5x0P" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
    /* Strict Black and White Theme */
    :root {
        --primary-bg: #fff; /* Pure White background */
        --secondary-bg: #f5f5f5; /* Very subtle off-white for contrast, nearly white */
        --dark-bg: #000; /* Pure Black sidebar/elements */
        --text-color: #000; /* Pure Black text */
        --light-text-color: #fff; /* Pure White text */
        --border-color: #ccc; /* Still a light gray for subtle borders, can be #000 if preferred */
        --button-primary: #333; /* Dark gray for primary actions, close to black */
        --button-hover: #000; /* Pure Black on hover */
        --button-success: #333; /* Dark gray for success, for consistency */
        --button-danger: #666; /* Medium gray for danger, desaturated from red */
        --button-warning: #666; /* Medium gray for warning/edit, desaturated from orange */
        --button-info: #333; /* Dark gray for info */
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--primary-bg);
        color: var(--text-color);
        display: flex;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        background-color: var(--dark-bg);
        color: var(--light-text-color);
        padding: 20px;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 5px rgba(0,0,0,0.2); /* Slightly darker shadow for contrast */
    }
    .sidebar .logo {
        font-size: 1.8rem;
        font-weight: bold;
        text-align: center;
        margin-bottom: 30px;
        color: #fff; /* White for logo */
    }
    .sidebar nav a {
        color: var(--light-text-color);
        text-decoration: none;
        padding: 12px 0;
        display: block;
        border-bottom: 1px solid #111; /* Darker border for sidebar links */
        transition: background-color 0.3s ease;
    }
    .sidebar nav a:hover {
        background-color: #111; /* Very dark gray on hover */
        color: #ffffff;
    }
    .main-content {
        flex-grow: 1;
        padding: 20px;
    }
    .topbar {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    .topbar .cart-btn {
        background-color: var(--button-success);
        color: var(--light-text-color);
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1.2rem;
        margin-left: 20px;
        transition: background-color 0.3s ease;
    }
    .topbar .cart-btn:hover {
        background-color: var(--button-hover);
    }
    .success-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: var(--button-success);
        color: var(--light-text-color);
        padding: 10px 20px;
        border-radius: 5px;
        display: none;
        z-index: 1050;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .success-notification.show {
        display: block;
    }
    .category-buttons {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .category-btn {
        background-color: var(--button-primary);
        color: var(--light-text-color);
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .category-btn:hover {
        background-color: var(--button-hover);
    }
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
    }
    .menu-item {
        background-color: var(--primary-bg); /* Item background is pure white */
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .menu-item img {
    max-width: 100%;
    height: 150px; /* Keep a fixed height for consistent row alignment */
    object-fit: contain; /* This will prevent stretching/distortion */
    border-radius: 4px;
    margin-bottom: 15px;
    /* If you still want grayscale, keep the line below. If not, remove it. */
    /* filter: grayscale(100%); */
    background-color: transparent; /* Ensure no background on the image itself */
}
    .menu-item .item-name {
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 1.1rem;
        color: var(--text-color);
    }
    .menu-item .item-price {
        color: #333; /* Dark gray for price, provides some contrast */
        margin-bottom: 15px;
        font-size: 1rem;
    }
    .menu-item .btn-add {
        background-color: var(--button-info);
        color: var(--light-text-color);
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .menu-item .btn-add:hover {
        background-color: var(--button-hover);
    }

    /* Bootstrap Modal Overrides for black & white theme */
    .modal-content {
        background-color: var(--secondary-bg); /* Modal background */
        color: var(--text-color);
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .modal-header {
        border-bottom: 1px solid var(--border-color);
        background-color: var(--primary-bg); /* Modal header background */
    }
    .modal-title {
        color: var(--text-color);
    }
    .btn-close {
        filter: invert(100%) grayscale(100%); /* Make close button visible on dark backgrounds */
    }
    .list-group-item {
        background-color: var(--primary-bg); /* List item background */
        border-color: var(--border-color);
        color: var(--text-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .list-group-item button.btn {
        margin-left: 5px;
    }
    .btn-warning { /* Edit button */
        background-color: var(--button-warning);
        border-color: var(--button-warning);
        color: white !important;
    }
    .btn-warning:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }
    .btn-danger { /* Delete button */
        background-color: var(--button-danger);
        border-color: var(--button-danger);
        color: white !important;
    }
    .btn-danger:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }
    .btn-success { /* Pay Now, Pay */
        background-color: var(--button-success);
        border-color: var(--button-success);
        color: white !important;
    }
    .btn-success:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }
    .btn-secondary { /* Close, Cancel */
        background-color: var(--button-primary);
        border-color: var(--button-primary);
        color: white !important;
    }
    .btn-secondary:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }
    .btn-primary { /* Save Changes */
        background-color: var(--button-primary);
        border-color: var(--button-primary);
        color: white !important;
    }
    .btn-primary:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }
    .btn-info { /* Add Billing, Hot/Cold */
        background-color: var(--button-info);
        border-color: var(--button-info);
        color: white !important;
    }
    .btn-info:hover {
        background-color: var(--button-hover);
        border-color: var(--button-hover);
    }


    .form-control {
        background-color: #fff; /* Pure white input background */
        border-color: var(--border-color);
        color: var(--text-color);
    }
    .form-control:focus {
        border-color: #333;
        box-shadow: 0 0 0 0.25rem rgba(0,0,0,0.15); /* Adjust shadow for black focus */
    }

    .receipt-popup {
        max-width: 400px;
    }
    #receiptContent {
        white-space: pre-wrap;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        margin-top: 20px;
        border-top: 1px dashed var(--border-color);
        padding-top: 15px;
        color: var(--text-color);
    }
    #receiptContent h3 {
        color: var(--text-color);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        body {
            flex-direction: column;
        }
        .sidebar {
            width: 100%;
            height: auto;
            padding: 15px;
            flex-direction: row;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
        }
        .sidebar .logo {
            margin-bottom: 0;
            margin-right: 20px;
        }
        .sidebar nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 10px;
        }
        .sidebar nav a {
            padding: 5px 10px;
            border-bottom: none;
        }
        .main-content {
            padding: 15px;
        }
        .topbar {
            justify-content: space-between;
        }
        .category-buttons {
            justify-content: center;
        }
        
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">HYPEBEANS</div>
    <nav>
        <a href="home.php"><i class="fas fa-home me-2"></i>Home</a>
        <a href="index.php"><i class="fas fa-order me-2"></i>Order Now</a>
        <a href="about.php"><i class="fas fa-info-circle me-2"></i>About</a>
        <a href="contact.php"><i class="fas fa-phone me-2"></i>Contact</a>
        <a href="analytics.php"><i class="fas fa-chart-bar me-2"></i>Analytics</a>
        <?php if ($isLoggedIn): ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Log In</a>
        <?php endif; ?>
    </nav>
</div>

<div class="main-content">
    <?php if ($isLoggedIn): ?>
        <div class="topbar">
            <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            <button class="cart-btn btn btn-dark" onclick="toggleCart()"><i class="fas fa-shopping-cart me-2"></i>Cart</button>
        </div>
    <?php endif; ?>

    <div id="success-notification" class="success-notification">
        ✅ Item Added!
    </div>

    <div class="category-buttons" id="category-buttons">
        <button class="category-btn btn" onclick="filterCategory('All')">All</button>
        <button class="category-btn btn" onclick="filterCategory('Espresso Based')">Espresso Based</button>
        <button class="category-btn btn" onclick="filterCategory('Cold Brew Specials')">Cold Brew Specials</button>
        <button class="category-btn btn" onclick="filterCategory('Cheesecake Frappe')">Cheesecake Frappe</button>
        <button class="category-btn btn" onclick="filterCategory('Pastries')">Pastries</button>
        <button class="category-btn btn" onclick="filterCategory('Sweets & Pastries')">Sweets & Pastries</button>
        <button class="category-btn btn" onclick="filterCategory('Pasta (Linguine)')">Pasta (Linguine)</button>
        <button class="category-btn btn" onclick="filterCategory('Snacks')">Snacks</button>
        <button class="category-btn btn" onclick="filterCategory('Add-ons')">Add-ons</button>
    </div>

    <div class="menu-grid" id="menuGrid">
       <?php
$menu = [
    'Espresso Based' => [
        ['Espresso', 69, true, 'coffee-espresso.jpg'],
        ['Americano', 99, true, 'coffee-americano.jpg'],
        ['Cappuccino', 109, true, 'coffee-cappuccino.jpg'],
        ['Latte', 109, true, 'coffee-latte.jpg'],
        ['Spanish Latte', 124, true, 'coffee-spanish-latte.jpg'],
        ['Caramel Macchiato', 139, true, 'coffee-caramel-macchiato.jpg'],
    ],
    'Cold Brew Specials' => [
        ['Spanish Latte (Cold)', 99, false, 'coldbrew-spanish-latte.jpg'],
        ['White Mocha (Cold)', 109, false, 'coldbrew-white-mocha.jpg'],
    ],
    'Cheesecake Frappe' => [
        ['Strawberry Cheesecake', 149, false, 'frappe-strawberry.jpg'],
        ['Red Velvet Cheesecake', 154, false, 'frappe-red-velvet.jpg'],
        ['Oreo Cheesecake', 154, false, 'frappe-oreo.jpg'],
        ['Biscoff Cheesecake', 159, false, 'frappe-biscoff.jpg'],
        ['Mango Graham Cheesecake', 159, false, 'frappe-mango.jpg'],
        ['Matcha Cheesecake', 169, false, 'frappe-matcha.jpg'],
    ],
    'Pastries' => [
        ['Banana Loaf (Slice)', 34, false, 'pastry-banana-loaf-slice.jpg'],
        ['Banana Loaf (3 Slices)', 99, false, 'pastry-banana-loaf-3slices.jpg'],
        ['Banana Loaf (Whole)', 299, false, 'pastry-banana-loaf-whole.jpg'],
    ],
    'Sweets & Pastries' => [
        ['Milk & White Chocolate Cookies (1 Pc)', 30, false, 'sweet-cookies-milk-white.jpg'],
        ['S\'mores Chocolate Cookies (1 Pc)', 30, false, 'sweet-cookies-smores.jpg'],
        ['Red Velvet Chocolate Cookies (1 Pc)', 30, false, 'sweet-cookies-red-velvet.jpg'],
        ['Nutella Brownies (1 Pc)', 35, false, 'sweet-brownies-nutella.jpg'],
    ],
    'Pasta (Linguine)' => [
        ['Creamy Tuna Carbonara', 139, false, 'pasta-carbonara.jpg'],
        ['Marinara with Meatballs', 144, false, 'pasta-marinara.jpg'],
    ],
    
    'Snacks' => [   
        ['BBQ/Cheese/Sour Cream & Onion Fries (Regular)', 64, false, 'snack-flavored-fries-reg.jpg'],
        ['BBQ/Cheese/Sour Cream & Onion Fries (Large)', 99, false, 'snack-flavored-fries-large.jpg'],
        ['Pop N\' Fries', 129, false, 'snack-pop-n-fries.jpg'],
        ['Chick Nuggets (6 Pcs)', 129, false, 'snack-chick-nuggets.jpg'],
        ['Chick Poppers', 149, false, 'snack-chick-poppers.jpg'],
        ['Tonkatsu Burger (w/ fries)', 149, false, 'snack-tonkatsu-burger.jpg'],
    ],
    'Add-ons' => [
        ['Espresso Shot', 30, false, 'addon-espresso.jpg'],
        ['Syrups (1 Pump)', 20, false, 'addon-syrup.jpg'],
        ['Garlic Sauce', 15, false, 'addon-garlic-sauce.jpg'],
        ['Ketchup', 5, false, 'addon-ketchup.jpg'],
    ]
];

        foreach ($menu as $category => $items) {
                foreach ($items as $item) {
                    $itemName = $item[0];
                    $itemPrice = $item[1];
                    $hasTempOption = isset($item[2]) ? $item[2] : false;
                    $imageFilename = isset($item[3]) ? $item[3] : 'placeholder.jpg'; // Default image if not set
                    $imagePath = 'images/' . $imageFilename; // Assuming images are in an 'images' folder
                    echo "<div class='menu-item' data-category='$category'>";
                    echo "<img src='" . htmlspecialchars($imagePath) . "' alt='" . htmlspecialchars($itemName) . "'>"; // Image tag
                    echo "<p class='item-name'>{$itemName}</p>";
                    echo "<p class='item-price'>₱{$itemPrice}</p>";
                    echo "<button class='btn btn-info btn-add' onclick='promptTemperature(\"" . htmlspecialchars(addslashes($itemName)) . "\", " . $itemPrice . ", " . ($hasTempOption ? 'true' : 'false') . ")'><i class='fas fa-plus me-2'></i>Add Billing</button>";
                    echo "</div>";
                }
            }
            // End of your provided PHP code
            ?>
        </div>
    </div>

    <div id="success-notification" class="notification">
        Item added to cart!
    </div>

    <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cartModalLabel">🛒 Your Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="toggleCart()"></button>
                </div>
                <div class="modal-body">
                    <ul id="cart-items-list" class="list-group list-group-flush"></ul>
                    <p class="mt-3 fs-5"><strong>Total: ₱<span id="cart-total">0</span></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="openPaymentModal()"><i class="fas fa-dollar-sign me-2"></i>Pay Now</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="toggleCart()"><i class="fas fa-times me-2"></i>Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">✏️ Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeEditModal()"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-item-index">
                    <div class="mb-3">
                        <label for="edit-item-name" class="form-label">Item Name:</label>
                        <input type="text" class="form-control" id="edit-item-name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-item-price" class="form-label">Price:</label>
                        <input type="number" class="form-control" id="edit-item-price" step="0.01" required>
                    </div>
                    <div id="edit-temperature-option" class="mb-3" style="display: none;">
                        <label for="edit-item-temperature" class="form-label">Temperature:</label>
                        <select class="form-select" id="edit-item-temperature">
                            <option value="">N/A</option>
                            <option value="Hot">Hot</option>
                            <option value="Cold">Cold</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="editItem()"><i class="fas fa-save me-2"></i>Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeEditModal()"><i class="fas fa-times me-2"></i>Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">💵 Complete Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closePaymentModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Customer Name:</label>
                        <input type="text" class="form-control" id="customer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="cash_amount" class="form-label">Cash Amount (₱):</label>
                        <input type="number" class="form-control" id="cash_amount" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="completePayment()"><i class="fas fa-check-circle me-2"></i>Pay</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closePaymentModal()"><i class="fas fa-times me-2"></i>Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content receipt-popup">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">🧾 Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeReceipt()"></button>
                </div>
                <div class="modal-body">
                    <div id="receiptContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeReceipt()"><i class="fas fa-times me-2"></i>Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="temperatureModal" tabindex="-1" aria-labelledby="temperatureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="temperatureModalLabel">Select Temperature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeTemperatureModal()"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="temp-item-name">
                    <input type="hidden" id="temp-item-price">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-dark" onclick="selectTemperature('Hot')">Hot ♨️</button>
                        <button type="button" class="btn btn-dark" onclick="selectTemperature('Cold')">Cold 🧊</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeTemperatureModal()"><i class="fas fa-times me-2"></i>Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
    // Initialize orders
    // Make sure $_SESSION['orders'] is always an array, even if empty, to prevent JSON errors
    let orders = <?php echo json_encode($_SESSION['orders'] ?? []); ?>;

    // Get Bootstrap modal instances
    // These must be initialized AFTER the Bootstrap JS script is loaded.
    const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    const temperatureModal = new bootstrap.Modal(document.getElementById('temperatureModal'));

    function updateCartList() {
        const cartList = document.getElementById('cart-items-list');
        const totalEl = document.getElementById('cart-total');
        const cartItemCount = document.getElementById('cart-item-count'); // Assuming you have this span in your cart button
        cartList.innerHTML = '';
        let total = 0;
        orders.forEach((order, index) => {
            total += parseFloat(order.price);
            const temperatureDisplay = order.temperature ? ` (${order.temperature})` : '';
            const li = document.createElement('li');
            li.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center'); // Bootstrap list group item
            li.innerHTML = `<span>${order.item}${temperatureDisplay} - ₱${order.price}</span>
                <div>
                    <button class="btn btn-sm btn-dark me-2" onclick="openEditModal(${index})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem(${index})"><i class="fas fa-trash-alt"></i></button>
                </div>`;
            cartList.appendChild(li);
        });
        totalEl.textContent = total.toFixed(2);
        cartItemCount.textContent = orders.length; // Update item count in the cart button
    }

    // Function to prompt for hot/cold
    function promptTemperature(item, price, hasTempOption) {
        if (hasTempOption) {
            document.getElementById('temp-item-name').value = item;
            document.getElementById('temp-item-price').value = price;
            temperatureModal.show(); // Show Bootstrap modal
        } else {
            // If no hot/cold option, add directly to cart
            addToCart(item, price, null);
        }
    }

    function selectTemperature(temperature) {
        const item = document.getElementById('temp-item-name').value;
        const price = parseFloat(document.getElementById('temp-item-price').value);
        addToCart(item, price, temperature);
        temperatureModal.hide(); // Hide Bootstrap modal
    }

    function closeTemperatureModal() {
        temperatureModal.hide(); // Hide Bootstrap modal
    }

    function addToCart(item, price, temperature) {
        let body = `action=add&item=${encodeURIComponent(item)}&price=${price}`;
        if (temperature) {
            body += `&temperature=${encodeURIComponent(temperature)}`;
        }

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok ' + res.statusText);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                orders = data.orders;
                updateCartList();
                showSuccess();
            } else {
                console.error("Error adding item:", data.error);
                alert("Failed to add item. See console for details.");
            }
        })
        .catch(error => {
            console.error("Fetch error during addToCart:", error);
            alert("An error occurred while adding to cart. Please try again.");
        });
    }

    function showSuccess() {
        const notif = document.getElementById('success-notification');
        notif.classList.add('show');
        setTimeout(() => notif.classList.remove('show'), 1000);
    }

    function toggleCart() {
        // Bootstrap 5 modals use the ._isShown property to check visibility
        if (cartModal._isShown) {
            cartModal.hide();
        } else {
            updateCartList(); // Update cart content before showing
            cartModal.show();
        }
    }

    function openEditModal(index) {
        const editItemNameInput = document.getElementById('edit-item-name');
        const editItemPriceInput = document.getElementById('edit-item-price');
        const editItemIndexInput = document.getElementById('edit-item-index');
        const editTemperatureOption = document.getElementById('edit-temperature-option');
        const editItemTemperatureSelect = document.getElementById('edit-item-temperature');

        const itemToEdit = orders[index];
        if (itemToEdit) {
            editItemNameInput.value = itemToEdit.item;
            editItemPriceInput.value = itemToEdit.price;
            editItemIndexInput.value = index;

            // Retrieve menu from PHP for comparison
            const menuItems = <?php echo json_encode($menu ?? []); ?>; // Ensure $menu is defined
            let originalHasTempOption = false;

            // Iterate through all categories and items to find a match
            for (const category in menuItems) {
                if (menuItems.hasOwnProperty(category)) {
                    const itemsInCat = menuItems[category];
                    for (let i = 0; i < itemsInCat.length; i++) {
                        const menuItem = itemsInCat[i];
                        // Compare by item name
                        if (menuItem[0] === itemToEdit.item) {
                            originalHasTempOption = menuItem[2]; // Get the hasTempOption value from the original menu
                            break; // Found the item, no need to continue searching
                        }
                    }
                    if (originalHasTempOption) {
                        break; // If found in a category, stop outer loop
                    }
                }
            }

            if (originalHasTempOption) {
                editTemperatureOption.style.display = 'block';
                editItemTemperatureSelect.value = itemToEdit.temperature || ''; // Set current temperature or empty
            } else {
                editTemperatureOption.style.display = 'none';
                editItemTemperatureSelect.value = ''; // Clear selection if no temp option
            }

            editModal.show(); // Show Bootstrap modal
        }
    }

    function closeEditModal() {
        editModal.hide(); // Hide Bootstrap modal
    }

    function editItem() {
        const index = document.getElementById('edit-item-index').value;
        const new_item = document.getElementById('edit-item-name').value;
        const new_price = parseFloat(document.getElementById('edit-item-price').value);
        const new_temperature = document.getElementById('edit-temperature-option').style.display === 'block' ?
                                document.getElementById('edit-item-temperature').value : null;

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=edit&index=${index}&new_item=${encodeURIComponent(new_item)}&new_price=${new_price}&new_temperature=${encodeURIComponent(new_temperature || '')}` // Ensure new_temperature is not 'null' string
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok ' + res.statusText);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                orders = data.orders;
                updateCartList();
                closeEditModal();
            } else {
                console.error("Error editing item:", data.error);
                alert("Failed to edit item. See console for details.");
            }
        })
        .catch(error => {
            console.error("Fetch error during editItem:", error);
            alert("An error occurred while editing the item. Please try again.");
        });
    }

    function deleteItem(index) {
        if (confirm('Are you sure you want to remove this item from the cart?')) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&index=${index}`
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok ' + res.statusText);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    orders = data.orders;
                    updateCartList();
                } else {
                    console.error("Error deleting item:", data.error);
                    alert("Failed to delete item. See console for details.");
                }
            })
            .catch(error => {
                console.error("Fetch error during deleteItem:", error);
                alert("An error occurred while deleting the item. Please try again.");
            });
        }
    }

    function filterCategory(category) {
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            if (category === 'All' || item.dataset.category === category) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function openPaymentModal() {
        cartModal.hide(); // Hide cart modal
        document.getElementById('customer_name').value = ''; // Clear previous input
        document.getElementById('cash_amount').value = ''; // Clear previous input
        paymentModal.show(); // Show payment modal
    }

    function closePaymentModal() {
        paymentModal.hide(); // Hide payment modal
    }

    function completePayment() {
        const customerName = document.getElementById('customer_name').value;
        const cashAmount = parseFloat(document.getElementById('cash_amount').value);
        const total = parseFloat(document.getElementById('cart-total').textContent);

        if (!customerName) {
            alert("Please enter customer name.");
            return;
        }
        if (isNaN(cashAmount) || cashAmount <= 0) {
            alert("Please enter a valid cash amount.");
            return;
        }
        if (cashAmount < total) {
            alert("Cash amount is less than the total. Please enter enough cash.");
            return;
        }

        const change = cashAmount - total;

        // Record sale in DB via AJAX
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=record_sale&total=${total}&customer_name=${encodeURIComponent(customerName)}&cash_amount=${cashAmount}` // Pass customer name and cash amount
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok ' + res.statusText);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                displayReceipt(customerName, total, cashAmount, change);
                paymentModal.hide(); // Hide payment modal
                receiptModal.show(); // Show receipt modal

                // Clear PHP session cart by making another AJAX call
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=clear_cart`
                })
                .then(res => res.json())
                .then(clearData => {
                    if (clearData.success) {
                        orders = []; // Clear local cart after successful sale and session clear
                        updateCartList(); // Update cart display
                    } else {
                        console.error("Error clearing session cart:", clearData.error);
                        alert("Failed to clear session cart. Cart might not be fully reset.");
                    }
                })
                .catch(clearError => {
                    console.error("Fetch error during clear_cart:", clearError);
                    alert("An error occurred while clearing the cart. Please refresh the page.");
                });

            } else {
                console.error("Error recording sale:", data.error);
                alert("Failed to record sale. See console for details.");
            }
        })
        .catch(error => {
            console.error("Fetch error during completePayment:", error);
            alert("An error occurred during payment. Please try again.");
        });
    }

    function displayReceipt(customerName, total, cash, change) {
        const receiptContent = document.getElementById('receiptContent');
        let receiptHTML = `<h3>HYPEBEANS RECEIPT</h3>`;
        receiptHTML += `Date: ${new Date().toLocaleString()}\n`;
        receiptHTML += `Customer: ${customerName}\n\n`;
        receiptHTML += `------------------------------------\n`;
        receiptHTML += `Item             Price\n`; // Adjusted spacing for better alignment
        receiptHTML += `------------------------------------\n`;
        orders.forEach(order => {
            const temp = order.temperature ? ` (${order.temperature})` : '';
            // Using a fixed width for item name and then appending price
            const itemLine = `${order.item}${temp}`;
            const padding = 25 - itemLine.length; // Adjust padding based on desired column width
            receiptHTML += `${itemLine}${' '.repeat(Math.max(0, padding))}₱${order.price.toFixed(2)}\n`;
        });
        receiptHTML += `------------------------------------\n`;
        receiptHTML += `Total:           ₱${total.toFixed(2)}\n`; // Adjusted spacing
        receiptHTML += `Cash:            ₱${cash.toFixed(2)}\n`;  // Adjusted spacing
        receiptHTML += `Change:          ₱${change.toFixed(2)}\n`; // Adjusted spacing
        receiptHTML += `------------------------------------\n`;
        receiptHTML += `Thank you for your purchase!\n`;
        receiptContent.innerText = receiptHTML; // Use innerText to preserve formatting
    }

    function closeReceipt() {
        receiptModal.hide();
    }

    // Initial cart update on page load
    document.addEventListener('DOMContentLoaded', updateCartList);

    </script>
</body>
</html>