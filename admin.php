<?php
session_start();
require_once 'connect.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function generate_csrf_token(): string {
    return bin2hex(random_bytes(32));
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

$admin_password = 'admin123';
$is_authenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    header("Location: /");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_authenticated) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['admin_authenticated'] = true;
        $is_authenticated = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_authenticated) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности: CSRF-токен недействителен.';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $code = htmlspecialchars($_POST['code'], ENT_QUOTES, 'UTF-8');
                $prize_code = htmlspecialchars($_POST['prize_code'], ENT_QUOTES, 'UTF-8');
                $activation_limit = filter_var($_POST['activation_limit'], FILTER_VALIDATE_INT);

                if (!empty($code) && !empty($prize_code) && $activation_limit > 0) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO promocodes (code, prize_code, activation_limit) VALUES (?, ?, ?)");
                        $stmt->execute([$code, $prize_code, $activation_limit]);
                        $success = "Промокод $code успешно создан!";
                    } catch (PDOException $e) {
                        $error = "Ошибка при создании промокода: " . $e->getMessage();
                    }
                } else {
                    $error = "Пожалуйста, заполните все поля корректно.";
                }
            } elseif ($_POST['action'] === 'edit_limit') {
                $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
                $activation_limit = filter_var($_POST['activation_limit'], FILTER_VALIDATE_INT);

                if ($activation_limit > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE promocodes SET activation_limit = ? WHERE id = ?");
                        $stmt->execute([$activation_limit, $id]);
                        $success = "Лимит активаций для промокода с ID $id обновлен!";
                    } catch (PDOException $e) {
                        $error = "Ошибка при обновлении лимита: " . $e->getMessage();
                    }
                } else {
                    $error = "Лимит активаций должен быть больше 0.";
                }
            }
        }
        $_SESSION['csrf_token'] = generate_csrf_token();
        header("Location: admin.php" . (isset($_GET['filter']) ? "?filter=" . urlencode($_GET['filter']) : ""));
        exit();
    }
}

$promocodes = $pdo->query("SELECT p.*, (SELECT COUNT(DISTINCT telegram_id) FROM promo_logs WHERE promocode_id = p.id) as unique_activations, (SELECT GROUP_CONCAT(DISTINCT telegram_id SEPARATOR ', ') FROM promo_logs WHERE promocode_id = p.id) as telegram_ids FROM promocodes p")->fetchAll();

$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, filter_var($_GET['page'], FILTER_VALIDATE_INT)) : 1;
$offset = ($page - 1) * $items_per_page;

$filter = isset($_GET['filter']) ? htmlspecialchars($_GET['filter'], ENT_QUOTES, 'UTF-8') : 'all';

if ($filter === 'all') {
    $total_logs = $pdo->query("SELECT COUNT(*) FROM promo_logs")->fetchColumn();
    $logs_query = "SELECT pl.id, pl.promocode_id, pl.telegram_id, pl.activated_at, p.code 
                   FROM promo_logs pl 
                   JOIN promocodes p ON pl.promocode_id = p.id 
                   ORDER BY pl.activated_at DESC 
                   LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($logs_query);
    $stmt->execute([$items_per_page, $offset]);
} else {
    $total_logs = $pdo->prepare("SELECT COUNT(*) FROM promo_logs pl JOIN promocodes p ON pl.promocode_id = p.id WHERE p.code = ?");
    $total_logs->execute([$filter]);
    $total_logs = $total_logs->fetchColumn();

    $logs_query = "SELECT pl.id, pl.promocode_id, pl.telegram_id, pl.activated_at, p.code 
                   FROM promo_logs pl 
                   JOIN promocodes p ON pl.promocode_id = p.id 
                   WHERE p.code = ? 
                   ORDER BY pl.activated_at DESC 
                   LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($logs_query);
    $stmt->execute([$filter, $items_per_page, $offset]);
}

$total_pages = ceil($total_logs / $items_per_page);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style type="text/css">
body {
    font-family: 'Nunito', sans-serif;
    background: linear-gradient(45deg, #FF4B2B, #FF416C);
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    color: #fff;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(to right, #2980B9, #6DD5FA, #FFF);
    z-index: -1;
    animation: animate 6s linear infinite;
}

@keyframes animate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.container {
    background-color: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 600px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.container:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
}

h1 {
    color: #fff;
    margin-bottom: 25px;
    font-size: 2rem;
    font-weight: 700;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
}

h2 {
    color: #fff;
    font-size: 1.5rem;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
    margin-top: 25px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #eee;
    text-align: left;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

input[type="text"], input[type="password"], input[type="number"] {
    width: 100%;
    padding: 10px 15px;
    margin-bottom: 20px;
    border: none;
    border-radius: 8px;
    background-color: rgba(255, 255, 255, 0.7);
    color: #333;
    font-size: 1rem;
    transition: background-color 0.3s ease;
    box-sizing: border-box;
}

input[type="text"]:focus, input[type="password"]:focus, input[type="number"]:focus {
    background-color: rgba(255, 255, 255, 0.9);
    outline: none;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
}

select {
    width: 100%;
    padding: 10px 15px;
    margin-bottom: 20px;
    border: none;
    border-radius: 8px;
    background-color: rgba(255, 255, 255, 0.7);
    color: #333;
    font-size: 1rem;
    transition: background-color 0.3s ease;
    box-sizing: border-box;
}

select:focus {
    background-color: rgba(255, 255, 255, 0.9);
    outline: none;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
}

button {
    background-color: #fff;
    color: #FF4B2B;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease, color 0.3s ease, background-color 0.3s ease;
}

button:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.5);
    color: #fff;
    background-color: #FF416C;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    overflow: hidden;
}

th, td {
    padding: 10px;
    text-align: left;
    color: #fff;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

th {
    background-color: rgba(255, 255, 255, 0.3);
    font-weight: 700;
}

tr:nth-child(even) {
    background-color: rgba(255, 255, 255, 0.1);
}

.telegram-ids {
    word-wrap: break-word;
    max-width: 150px;
}

.error {
    color: #FFFF00;
    margin-top: 15px;
    font-weight: 600;
    font-size: 0.9rem;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

.success {
    color: #fff;
    margin-top: 15px;
    font-weight: 600;
    font-size: 0.9rem;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

.pagination {
    margin-top: 15px;
}

.pagination a {
    color: #FF4B2B;
    text-decoration: none;
    padding: 8px 12px;
    background-color: #fff;
    border-radius: 5px;
    margin: 0 3px;
    font-weight: 600;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.pagination a:hover {
    background-color: #FF416C;
    color: #fff;
}

.pagination .current {
    background-color: #FF416C;
    color: #fff;
}

.logout {
    margin-top: 25px;
}

.logout a {
    color: #FF4B2B;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    padding: 12px 30px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    transition: background-color 0.3s ease, transform 0.3s ease, color 0.3s ease;
    display: inline-block;
}

.logout a:hover {
    background-color: #FF416C;
    transform: scale(1.05);
    color: #fff;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
    text-align: center;
    color: #333;
}

.modal-content h3 {
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 700;
}

.modal-content label {
    color: #333;
    text-shadow: none;
}

.modal-content input[type="number"] {
    background-color: #fff;
    margin-bottom: 20px;
}

.modal-content button {
    margin: 10px 5px;
    padding: 10px 20px;
}

.close-btn {
    background-color: #ccc;
    color: #333;
}

.close-btn:hover {
    background-color: #999;
    color: #fff;
    transform: scale(1.05);
}

@media (max-width: 600px) {
    .container {
        padding: 20px;
        width: 95%;
        max-width: 100%;
    }
    h1 {
        font-size: 1.5rem;
        margin-bottom: 20px;
    }
    h2 {
        font-size: 1.2rem;
        margin-top: 20px;
    }
    label {
        font-size: 0.9rem;
    }
    input[type="text"], input[type="password"], input[type="number"], select {
        padding: 8px 12px;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }
    button {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    th, td {
        padding: 8px;
        font-size: 0.8rem;
    }
    .telegram-ids {
        max-width: 100px;
    }
    .pagination a {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
    .logout a {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    .modal-content {
        width: 85%;
        padding: 15px;
    }
    .modal-content h3 {
        font-size: 1.2rem;
    }
    .modal-content button {
        padding: 8px 15px;
        font-size: 0.8rem;
    }
}

@media (min-width: 601px) and (max-width: 900px) {
    .container {
        padding: 25px;
        max-width: 500px;
    }
    h1 {
        font-size: 1.8rem;
    }
    h2 {
        font-size: 1.3rem;
    }
    label {
        font-size: 1rem;
    }
    input[type="text"], input[type="password"], input[type="number"], select {
        padding: 10px 15px;
    }
    button {
        padding: 12px 25px;
    }
    th, td {
        font-size: 0.9rem;
    }
    .telegram-ids {
        max-width: 120px;
    }
    .modal-content {
        width: 80%;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_authenticated): ?>
            <h1>Вход в админ-панель</h1>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Войти</button>
            </form>
        <?php else: ?>
            <h1>Админ-панель</h1>

            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <h2>Создать промокод</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="create">
                <label for="code">Промокод:</label>
                <input type="text" id="code" name="code" required>
                <label for="prize_code">Код приза:</label>
                <input type="text" id="prize_code" name="prize_code" required>
                <label for="activation_limit">Лимит активаций:</label>
                <input type="number" id="activation_limit" name="activation_limit" min="1" value="1" required>
                <button type="submit">Создать</button>
            </form>

            <?php if (!empty($promocodes)): ?>
                <h2>Список промокодов</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Промокод</th>
                        <th>Код приза</th>
                        <th>Использован</th>
                        <th>Активации</th>
                        <th>Лимит</th>
                        <th>Telegram ID</th>
                        <th>Действия</th>
                    </tr>
                    <?php foreach ($promocodes as $promo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($promo['id']); ?></td>
                            <td><?php echo htmlspecialchars($promo['code']); ?></td>
                            <td><?php echo htmlspecialchars($promo['prize_code']); ?></td>
                            <td><?php echo $promo['is_used'] ? 'Да' : 'Нет'; ?></td>
                            <td><?php echo htmlspecialchars($promo['unique_activations']); ?></td>
                            <td><?php echo htmlspecialchars($promo['activation_limit']); ?></td>
                            <td class="telegram-ids"><?php echo htmlspecialchars($promo['telegram_ids'] ?: 'Нет'); ?></td>
                            <td>
                                <button onclick="openModal('modal-<?php echo $promo['id']; ?>')">Изменить</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php foreach ($promocodes as $promo): ?>
                    <div id="modal-<?php echo $promo['id']; ?>" class="modal">
                        <div class="modal-content">
                            <h3>Редактировать промокод <?php echo htmlspecialchars($promo['code']); ?></h3>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="edit_limit">
                                <input type="hidden" name="id" value="<?php echo $promo['id']; ?>">
                                <label for="activation_limit-<?php echo $promo['id']; ?>">Лимит активаций:</label>
                                <input type="number" id="activation_limit-<?php echo $promo['id']; ?>" name="activation_limit" min="1" value="<?php echo htmlspecialchars($promo['activation_limit']); ?>" required>
                                <button type="submit">Сохранить</button>
                                <button type="button" class="close-btn" onclick="closeModal('modal-<?php echo $promo['id']; ?>')">Закрыть</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2>Логи активаций</h2>
            <form method="get">
                <label for="filter">Фильтр по промокоду:</label>
                <select id="filter" name="filter" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все</option>
                    <?php foreach ($promocodes as $promo): ?>
                        <option value="<?php echo htmlspecialchars($promo['code']); ?>" <?php echo $filter === $promo['code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($promo['code']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Промокод</th>
                    <th>Telegram ID</th>
                    <th>Дата активации</th>
                </tr>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['id']); ?></td>
                        <td><?php echo htmlspecialchars($log['code']); ?></td>
                        <td><?php echo htmlspecialchars($log['telegram_id']); ?></td>
                        <td><?php echo htmlspecialchars($log['activated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page - 1; ?>">Предыдущая</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'current' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page + 1; ?>">Следующая</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="logout">
                <a href="?logout">Выйти</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.onclick = function(event) {
        var modals = document.getElementsByClassName('modal');
        for (var i = 0; i < modals.length; i++) {
            if (event.target == modals[i]) {
                modals[i].style.display = 'none';
            }
        }
    }
    </script>
</body>
</html>