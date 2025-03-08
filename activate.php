<?php
require_once 'connect.php';
session_start();

function escapeHtml(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(400);
        $result = ['status' => 'error', 'message' => 'Ошибка безопасности: CSRF-токен недействителен.'];
        goto renderResult;
    }

    $promocode = filter_var($_POST["promocode"], FILTER_SANITIZE_STRING);
    $telegram_id = filter_var($_POST["telegram_id"], FILTER_SANITIZE_STRING);

    if (empty($promocode) || empty($telegram_id)) {
        http_response_code(400);
        $result = ['status' => 'error', 'message' => 'Пожалуйста, заполните все поля.'];
        goto renderResult;
    }

    if (!preg_match('/^@[\w]+$/', $telegram_id)) {
        http_response_code(400);
        $result = ['status' => 'error', 'message' => 'Telegram ID должен начинаться с "@" и содержать только буквы, цифры или подчеркивания.'];
        goto renderResult;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, prize_code, is_used, telegram_id, activation_limit FROM promocodes WHERE code = ?");
        $stmt->execute([$promocode]);
        $promocode_data = $stmt->fetch();

        if ($promocode_data) {
            $activation_count = $pdo->prepare("SELECT COUNT(*) FROM promo_logs WHERE promocode_id = ?");
            $activation_count->execute([$promocode_data['id']]);
            $current_activations = $activation_count->fetchColumn();

            $user_activation = $pdo->prepare("SELECT COUNT(*) FROM promo_logs WHERE promocode_id = ? AND telegram_id = ?");
            $user_activation->execute([$promocode_data['id'], $telegram_id]);
            $user_activation_count = $user_activation->fetchColumn();

            if ($user_activation_count > 0) {
                http_response_code(409);
                $result = ['status' => 'error', 'message' => 'Вы уже активировали этот промокод с данным Telegram ID.'];
                goto renderResult;
            }

            if ($current_activations < $promocode_data['activation_limit']) {
                $prize_code = escapeHtml($promocode_data['prize_code']);
                $update_stmt = $pdo->prepare("UPDATE promocodes SET telegram_id = ? WHERE id = ?");
                $update_stmt->execute([$telegram_id, $promocode_data['id']]);

                $log_stmt = $pdo->prepare("INSERT INTO promo_logs (promocode_id, telegram_id) VALUES (?, ?)");
                $log_stmt->execute([$promocode_data['id'], $telegram_id]);

                if ($current_activations + 1 == $promocode_data['activation_limit']) {
                    $update_stmt = $pdo->prepare("UPDATE promocodes SET is_used = 1 WHERE id = ?");
                    $update_stmt->execute([$promocode_data['id']]);
                }

                $result = [
                    'status' => 'success',
                    'message' => 'Промокод успешно активирован!',
                    'prize_code' => $prize_code,
                    'telegram_id' => $telegram_id
                ];
            } else {
                http_response_code(409);
                $result = ['status' => 'error', 'message' => 'Лимит активаций промокода исчерпан.'];
                goto renderResult;
            }
        } else {
            http_response_code(404);
            $result = ['status' => 'error', 'message' => 'Неверный промокод.'];
            goto renderResult;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Ошибка базы данных: " . $e->getMessage());
        $result = ['status' => 'error', 'message' => 'Произошла ошибка. Пожалуйста, попробуйте позже.'];
        goto renderResult;
    }
} else {
    header("Location: index.php");
    exit();
}

renderResult:
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результат активации</title>
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
    overflow: hidden;
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
    padding: 50px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    width: 550px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.container:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
}

h1 {
    color: #fff;
    margin-bottom: 40px;
    font-size: 3rem;
    font-weight: 700;
    text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.5);
}

label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #eee;
    text-align: left;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

input[type="text"] {
    width: 100%;
    padding: 15px 20px;
    margin-bottom: 30px;
    border: none;
    border-radius: 10px;
    background-color: rgba(255, 255, 255, 0.7);
    color: #333;
    font-size: 1.1rem;
    transition: background-color 0.3s ease;
}

input[type="text"]:focus {
    background-color: rgba(255, 255, 255, 0.9);
    outline: none;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
}

button {
    background-color: #fff;
    color: #FF4B2B;
    padding: 16px 40px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.2rem;
    font-weight: 700;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease, color 0.3s ease, background-color 0.3s ease;
}

button:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
    color: #fff;
    background-color: #FF416C;
}

.error {
    color: #FFFF00;
    margin-top: 20px;
    font-weight: 600;
    font-size: 1.1rem;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
}

.result-container {
    padding: 60px;
}

.success-heading {
    color: #fff;
    font-size: 3.5rem;
    margin-bottom: 30px;
    font-weight: 700;
    text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.5);
}

.error-heading {
    color: #FFFF00;
    font-size: 3.5rem;
    margin-bottom: 30px;
    font-weight: 700;
    text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.5);
}

.success-message {
    font-size: 1.3rem;
    color: #eee;
    margin-bottom: 40px;
    line-height: 1.7;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
}

.error-message {
    font-size: 1.3rem;
    color: #eee;
    margin-bottom: 40px;
    line-height: 1.7;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
}

.prize-info {
    margin-bottom: 40px;
    text-align: left;
    padding: 30px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.prize-info p {
    margin: 15px 0;
    font-size: 1.2rem;
    color: #fff;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
}

.prize-code, .telegram-id {
    font-weight: 700;
    color: #FFFF00;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
    transition: color 0.3s ease;
}

.copy-code {
    cursor: pointer;
}

.copy-code:hover {
    color: #fff;
}

.back-link {
    margin-top: 40px;
}

.back-link a {
    color: #FF4B2B;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.2rem;
    padding: 16px 40px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    transition: background-color 0.3s ease, transform 0.3s ease, color 0.3s ease;
    display: inline-block;
}

.back-link a:hover {
    background-color: rgba(255, 255, 255, 0.8);
    transform: scale(1.1);
    color: #FF416C;
}

@media (min-width: 768px) {
    .container {
        width: 650px;
        padding: 60px;
    }
    h1 {
        font-size: 3.5rem;
        margin-bottom: 50px;
    }
    label {
        font-size: 1.2rem;
    }
    input[type="text"] {
        padding: 18px 25px;
        margin-bottom: 40px;
        font-size: 1.2rem;
    }
    button {
        font-size: 1.3rem;
        padding: 18px 45px;
    }
    .result-container {
        padding: 80px;
    }
    .success-heading,
    .error-heading {
        font-size: 4rem;
        margin-bottom: 40px;
    }
    .success-message,
    .error-message {
        font-size: 1.4rem;
        margin-bottom: 50px;
    }
    .prize-info {
        padding: 40px;
    }
    .prize-info p {
        font-size: 1.3rem;
    }
    .back-link a {
        font-size: 1.3rem;
        padding: 18px 45px;
    }
}

@media (min-width: 1200px) {
    .container {
        width: 750px;
        padding: 70px;
    }
    h1 {
        font-size: 4rem;
        margin-bottom: 60px;
    }
    label {
        font-size: 1.3rem;
    }
    input[type="text"] {
        padding: 20px 30px;
        margin-bottom: 50px;
        font-size: 1.4rem;
    }
    button {
        font-size: 1.5rem;
        padding: 20px 50px;
    }
    .result-container {
        padding: 100px;
    }
    .success-heading,
    .error-heading {
        font-size: 5rem;
        margin-bottom: 60px;
    }
    .success-message,
    .error-message {
        font-size: 1.6rem;
        margin-bottom: 60px;
    }
    .prize-info {
        padding: 50px;
    }
    .prize-info p {
        font-size: 1.5rem;
    }
    .back-link a {
        font-size: 1.5rem;
        padding: 20px 50px;
    }
}
    </style>
</head>
<body>
    <div class="container result-container">
        <?php if (isset($result)): ?>
            <?php if ($result['status'] === 'success'): ?>
                <h1 class="success-heading">Поздравляем!</h1>
                <p class="success-message"><?= escapeHtml($result['message']) ?></p>
                <div class="prize-info">
                    <p>Ваш код приза: <strong class="prize-code copy-code" onclick="copyToClipboard('<?= escapeHtml($result['prize_code']) ?>')"><?= escapeHtml($result['prize_code']) ?></strong></p>
                    <p>Telegram ID: <strong class="telegram-id"><?= escapeHtml($result['telegram_id']) ?></strong></p>
                </div>
            <?php else: ?>
                <h1 class="error-heading">Ошибка!</h1>
                <p class="error-message"><?= escapeHtml($result['message']) ?></p>
            <?php endif; ?>
            <div class="back-link">
                <a href="index.php">Вернуться на главную страницу</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
    function copyToClipboard(text) {
      navigator.clipboard.writeText(text)
        .then(() => {
          alert('Код приза скопирован в буфер обмена: ' + text);
        })
        .catch(err => {
          console.error('Не удалось скопировать код в буфер обмена: ', err);
          alert('Не удалось скопировать код. Пожалуйста, попробуйте вручную.');
        });
    }
    </script>
</body>
</html>