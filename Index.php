<?php
session_start();
$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] === 'admin';

// Database connection details (replace with your actual credentials)
$servername = "localhost";
$username = "root"; // Your database username
$password = "";      // Your database password
$dbname = "hybeans";  // Your database name

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
    <title>Hypebeans Coffee Shop</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="sidebar">
    <div class="logo">"HYPEBEANS"</div>
    <nav>
        <a href="index.php">🏠HOME</a>
        <a href="about.php">ℹ️ABOUT</a>
        <a href="contact.php">📞CONTACT</a>
        <a href="analytics.php">📊ANALYTICS</a>
        <?php if ($isLoggedIn): ?>
            <a href="logout.php">🚪LOGOUT</a>
             <?php else: ?>
            <a href="login.php">🔑Log In</a>
        <?php endif; ?>
    </nav>
</div>

<div class="main-content">
    <?php if ($isLoggedIn): ?>
        <div class="topbar">
            <i class="fa-solid fa-user-circle"></i>
            <button class="cart-btn" onclick="toggleCart()">🛒</button>
        </div>
    <?php endif; ?>

    <div id="success-notification" class="success-notification">
        ✅ Item Added!
    </div>

    <div class="category-buttons" id="category-buttons">
        <button class="category-btn" onclick="filterCategory('All')">All</button>
        <button class="category-btn" onclick="filterCategory('Espresso Based')">Espresso Based</button>
        <button class="category-btn" onclick="filterCategory('Cold Brew Specials')">Cold Brew Specials</button>
        <button class="category-btn" onclick="filterCategory('Cheesecake Frappe')">Cheesecake Frappe</button>
        <button class="category-btn" onclick="filterCategory('Pastries')">Pastries</button>
        <button class="category-btn" onclick="filterCategory('Sweets & Pastries')">Sweets & Pastries</button>
        <button class="category-btn" onclick="filterCategory('Pasta (Linguine)')">Pasta (Linguine)</button>
        <button class="category-btn" onclick="filterCategory('Add-ons')">Add-ons</button>
    </div>

    <div class="menu-grid" id="menuGrid">
        <?php
        $menu = [
            'Espresso Based' => [
                ['Espresso ☕', 69, true], // true indicates hot/cold option is available
                ['Americano ☕', 99, true],
                ['Cappuccino ☕', 109, true],
                ['Latte ☕', 109, true],
                ['Spanish Latte ☕', 124, true],
                ['Caramel Macchiato ☕', 139, true],
            ],
            'Cold Brew Specials' => [
                ['Spanish Latte (Cold) 🧊', 99, false], // false indicates no hot/cold option
                ['White Mocha (Cold) 🧊', 109, false],
            ],
            'Cheesecake Frappe' => [
                ['Strawberry Cheesecake 🍓', 149, false],
                ['Red Velvet Cheesecake ❤️', 154, false],
                ['Oreo Cheesecake 🍪', 154, false],
                ['Biscoff Cheesecake 🍯', 159, false],
                ['Mango Graham Cheesecake 🥭', 159, false],
                ['Matcha Cheesecake 🍵', 169, false],
            ],
            'Pastries' => [
                ['Banana Loaf (Slice) 🍌', 34, false],
                ['Banana Loaf (3 Slices) 🍌', 99, false],
                ['Banana Loaf (Whole) 🍌', 299, false],
            ],
            'Sweets & Pastries' => [
                ['Milk & White Choco Cookies 🍪 (1 Pc)', 30, false],
                ['S\'mores Cookies 🍫 (1 Pc)', 30, false],
                ['Red Velvet Cookies ❤️ (1 Pc)', 30, false],
                ['Nutella Brownies 🍫 (1 Pc)', 35, false],
            ],
            'Pasta (Linguine)' => [
                ['Creamy Tuna Carbonara 🍝', 139, false],
                ['Marinara with Meatballs 🍝', 144, false],
            ],
            'Add-ons' => [
                ['Espresso Shot ➕', 30, false],
                ['Syrups (1 Pump) 🍯', 20, false],
                ['Garlic Sauce 🧄', 15, false],
                ['Ketchup 🍅', 5, false],
            ]
        ];

        foreach ($menu as $category => $items) {
            foreach ($items as $item) {
                $itemName = $item[0];
                $itemPrice = $item[1];
                $hasTempOption = isset($item[2]) ? $item[2] : false; // Check if hot/cold option is available
                echo "<div class='menu-item' data-category='$category'>";
                echo "<p class='item-name'>{$itemName}</p>";
                echo "<p class='item-price'>₱{$itemPrice}</p>";
                echo "<button class='btn-add' onclick='promptTemperature(\"" . $itemName . "\", " . $itemPrice . ", " . ($hasTempOption ? 'true' : 'false') . ")'>➕ Add Billing</button>";
                echo "</div>";
            }
        }
        ?>
    </div>

    <div id="cartModal" class="modal">
        <div class="modal-content cart-popup">
            <h2>🛒 Your Cart</h2>
            <ul id="cart-items-list"></ul>
            <p><strong>Total: ₱<span id="cart-total">0</span></strong></p>
            <div class="cart-actions">
                <button onclick="openPaymentModal()" class="btn-add">💵 Pay Now</button>
                <button onclick="toggleCart()" class="btn-add btn-cancel">❌ Close</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ Edit Item</h3>
            <input type="hidden" id="edit-item-index">
            <label for="edit-item-name">Item Name:</label>
            <input type="text" id="edit-item-name" required><br><br>
            <label for="edit-item-price">Price:</label>
            <input type="number" id="edit-item-price" step="0.01" required><br><br>
            <div id="edit-temperature-option" style="display: none;">
                <label for="edit-item-temperature">Temperature:</label>
                <select id="edit-item-temperature">
                    <option value="">N/A</option>
                    <option value="Hot">Hot</option>
                    <option value="Cold">Cold</option>
                </select><br><br>
            </div>
            <button onclick="editItem()" class="btn-add">💾 Save Changes</button>
            <button onclick="closeEditModal()" class="btn-add btn-cancel">❌ Cancel</button>
        </div>
    </div>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h3>💵 Complete Payment</h3>
            <label for="customer_name">Customer Name:</label>
            <input type="text" id="customer_name" required>
            <label for="cash_amount">Cash Amount (₱):</label>
            <input type="number" id="cash_amount" required>
            <button onclick="completePayment()" class="btn-add">✅ Pay</button>
            <button onclick="closePaymentModal()" class="btn-add btn-cancel">❌ Cancel</button>
        </div>
    </div>

    <div id="receiptModal" class="modal">
        <div class="modal-content receipt-popup">
            <h2>🧾 Receipt</h2>
            <div id="receiptContent"></div>
            <button onclick="closeReceipt()" class="close-btn" style="margin-top: 15px;">❌ Close</button>
        </div>
    </div>

    <div id="temperatureModal" class="modal">
        <div class="modal-content">
            <h3>Select Temperature</h3>
            <input type="hidden" id="temp-item-name">
            <input type="hidden" id="temp-item-price">
            <button onclick="selectTemperature('Hot')" class="btn-add">Hot ♨️</button>
            <button onclick="selectTemperature('Cold')" class="btn-add">Cold 🧊</button>
            <button onclick="closeTemperatureModal()" class="btn-add btn-cancel">Cancel</button>
        </div>
    </div>

</div>

<script>
// Initialize orders
let orders = <?php echo json_encode($_SESSION['orders']); ?>;

function updateCartList() {
    const cartList = document.getElementById('cart-items-list');
    const totalEl = document.getElementById('cart-total');
    cartList.innerHTML = '';
    let total = 0;
    orders.forEach((order, index) => {
        total += parseFloat(order.price);
        const temperatureDisplay = order.temperature ? ` (${order.temperature})` : '';
        const li = document.createElement('li');
        li.innerHTML = `<span>${order.item}${temperatureDisplay} - ₱${order.price}</span>
            <div>
                <button onclick="openEditModal(${index})">✏️</button>
                <button onclick="deleteItem(${index})">🗑️</button>
            </div>`;
        cartList.appendChild(li);
    });
    totalEl.textContent = total.toFixed(2);
}

// Function to prompt for hot/cold
function promptTemperature(item, price, hasTempOption) {
    if (hasTempOption) {
        document.getElementById('temp-item-name').value = item;
        document.getElementById('temp-item-price').value = price;
        document.getElementById('temperatureModal').classList.add('show');
    } else {
        // If no hot/cold option, add directly to cart
        addToCart(item, price, null);
    }
}

function selectTemperature(temperature) {
    const item = document.getElementById('temp-item-name').value;
    const price = parseFloat(document.getElementById('temp-item-price').value);
    addToCart(item, price, temperature);
    closeTemperatureModal();
}

function closeTemperatureModal() {
    document.getElementById('temperatureModal').classList.remove('show');
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
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            orders = data.orders;
            updateCartList();
            showSuccess();
        }
    });
}

function showSuccess() {
    const notif = document.getElementById('success-notification');
    notif.classList.add('show');
    setTimeout(() => notif.classList.remove('show'), 1000);
}

function toggleCart() {
    const modal = document.getElementById('cartModal');
    modal.classList.toggle('show');
    updateCartList();
}

function openEditModal(index) {
    const editModal = document.getElementById('editModal');
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

        // Check if the item originally had a temperature option
        const menuItems = <?php echo json_encode($menu); ?>;
        let originalHasTempOption = false;
        for (const category in menuItems) {
            for (const menuItem of menuItems[category]) {
                if (menuItem[0] === itemToEdit.item) { // Compare with original item name
                    originalHasTempOption = menuItem[2] || false;
                    break;
                }
            }
            if (originalHasTempOption) break;
        }

        if (originalHasTempOption) {
            editTemperatureOption.style.display = 'block';
            editItemTemperatureSelect.value = itemToEdit.temperature || ''; // Set current temperature
        } else {
            editTemperatureOption.style.display = 'none';
            editItemTemperatureSelect.value = '';
        }

        editModal.classList.add('show');
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function editItem() {
    const index = document.getElementById('edit-item-index').value;
    const newItem = document.getElementById('edit-item-name').value;
    const newPrice = parseFloat(document.getElementById('edit-item-price').value);
    const newTemperature = document.getElementById('edit-item-temperature').value;

    if (!newItem || isNaN(newPrice)) {
        alert('Please enter valid item name and price.');
        return;
    }

    let body = `action=edit&index=${index}&new_item=${encodeURIComponent(newItem)}&new_price=${newPrice}`;
    if (newTemperature) {
        body += `&new_temperature=${encodeURIComponent(newTemperature)}`;
    }

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            orders = data.orders;
            updateCartList();
            closeEditModal();
        } else {
            console.error('Failed to edit item:', data.error);
        }
    });
}

function openPaymentModal() {
    document.getElementById('paymentModal').classList.add('show');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('show');
}

function completePayment() {
    const name = document.getElementById('customer_name').value;
    const cash = parseFloat(document.getElementById('cash_amount').value);
    const total = orders.reduce((sum, order) => sum + parseFloat(order.price), 0);

    if (!name || isNaN(cash)) {
        alert('Please fill all fields!');
        return;
    }
    if (cash < total) {
        alert('Cash not enough!');
        return;
    }

    const change = (cash - total).toFixed(2);

    let receipt = `
    <div style="font-family: 'Courier New', Courier, monospace; padding: 10px;">
        <h3 style="text-align: center;">🧾 Hypebeans Coffee Receipt</h3>
        <p>Date: <strong>${new Date().toLocaleString()}</strong></p>
        <p>Customer Name: <strong>${name}</strong></p>
        <hr>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th style="text-align: right;">Price (₱)</th>
                </tr>
            </thead>
            <tbody>`;
orders.forEach(order => {
    const temperatureInReceipt = order.temperature ? ` (${order.temperature})` : '';
    receipt += `
        <tr>
            <td>${order.item}${temperatureInReceipt}</td>
            <td style="text-align: right;">${parseFloat(order.price).toFixed(2)}</td>
        </tr>`;
});
receipt += `
            </tbody>
        </table>
        <hr>
        <p><strong>Total:</strong> <span style="float: right;">₱${total.toFixed(2)}</span></p>
        <p><strong>Cash:</strong> <span style="float: right;">₱${cash.toFixed(2)}</span></p>
        <p><strong>Change:</strong> <span style="float: right;">₱${change}</span></p>
        <hr>
        <p style="text-align: center;">Thank you for dining at Hypebeans! ☕</p>
    </div>`;


    document.getElementById('receiptContent').innerHTML = receipt;

    document.getElementById('paymentModal').classList.remove('show');
    document.getElementById('cartModal').classList.remove('show');
    document.getElementById('receiptModal').classList.add('show');

    // Save the sale to the database
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=record_sale&total=${total}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log('Sale recorded successfully in the database.');
            // Clear the local orders array after a successful sale and update the cart display
            orders = [];
            updateCartList();
        } else {
            console.error('Failed to record sale in the database:', data.error);
        }
    });

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clear'
    }).then(res => res.json()).then(() => orders = []);
}


function closeReceipt() {
    document.getElementById('receiptModal').classList.remove('show');
}

function deleteItem(index) {
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&index=${index}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            orders = data.orders;
            updateCartList();
        }
    });
}

// Filtering Categories
function filterCategory(category) {
    document.querySelectorAll('.menu-item').forEach(item => {
        if (category === 'All' || item.dataset.category === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Initial cart update when the page loads
document.addEventListener('DOMContentLoaded', updateCartList);
</script>

</body>
</html>