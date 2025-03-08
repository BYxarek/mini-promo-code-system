<?php
session_start();

function generate_csrf_token(): string {
    return bin2hex(random_bytes(32));
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Активация промокода</title>
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
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
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
    <div class="container">
        <h1>Активация промокода</h1>
        <form action="activate.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="telegram_id">Ваш Telegram ID:</label>
            <input type="text" id="telegram_id" name="telegram_id" required placeholder="@username или ID">
            <label for="promocode">Введите промокод:</label>
            <input type="text" id="promocode" name="promocode" required>
            <button type="submit">Активировать</button>
        </form>
    </div>
    <script>
        const telegramInput = document.getElementById('telegram_id');
        telegramInput.addEventListener('input', () => {
            const value = telegramInput.value;
            if (!value.match(/^@[\w]+$/) && !value.match(/^\d+$/)) {
                telegramInput.setCustomValidity('Введите корректный Telegram ID');
            } else {
                telegramInput.setCustomValidity('');
            }
        });
    </script>
</body>
</html>