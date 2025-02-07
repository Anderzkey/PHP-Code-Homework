<?php
// Определяем токен и файл спам номеров
define('SECRET_TOKEN', 'MY_SECRET_TOKEN');
define('SPAM_FILE', 'spam_numbers.txt');

// Проверка добавляем ли мы спам номер
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'add':
            handleAddRequest();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    handleDefaultRequest();
}

// Функция для чтения файла спам номеров
function handleDefaultRequest() {
    // Читаем файл спам номеров
    if (file_exists(SPAM_FILE)) {
        $spamNumbers = file(SPAM_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        sort($spamNumbers);
    } else {
        $spamNumbers = array();
    }

    // Считываем ввод юзера
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = trim($_POST['phone_number']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = trim($_GET['phone_number']);
    } else {
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    // Приводим номер к формату "+7xxxxxxxxxx"
    $normalizedInput = normalizeNumber($input);

    // Используем функцию бинарного поиска для определения спам (или нет) номера
    if (binarySearch($spamNumbers, $normalizedInput)) {
        echo json_encode(["status" => "spam"]);
    } else {
        echo json_encode(["status" => "normal"]);
    }
}

// Функция добавляет введеный юзером номер в спам номера
function handleAddRequest() {
    // Проверка метода на POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit;
    }

    // Проверка соответствия токена
    if (!isset($_POST['token']) || $_POST['token'] !== SECRET_TOKEN) {
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    // Получение номера
    if (!isset($_POST['phone_number'])) {
        echo json_encode(['error' => 'Phone number not provided']);
        exit;
    }

    $input = trim($_POST['phone_number']);

    // Форматирование номера
    $normalizedNumber = normalizeNumber($input);

    // Валидирование номера
    if (!isValidPhoneNumber($normalizedNumber)) {
        echo json_encode(['error' => 'Invalid phone number']);
        exit;
    }

    // Чтение файла спам номеров
    if (!file_exists(SPAM_FILE)) {
        // Create the file if it doesn't exist
        if (!touch(SPAM_FILE)) {
            echo json_encode(['error' => 'Failed to create spam file']);
            exit;
        }
    }

    $spamNumbers = file(SPAM_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Проверка есть ли номер уже в спам файле
    if (binarySearch($spamNumbers, $normalizedNumber)) {
        echo json_encode(['error' => 'Phone number already exists']);
        exit;
    }

    // Добавление номера
    $spamNumbers[] = $normalizedNumber;

    // Сортировка
    sort($spamNumbers);

    // Открываем в режиме записи
    $file = fopen(SPAM_FILE, 'w');
    if ($file === false) {
        echo json_encode(['error' => 'Failed to open spam file for writing']);
        exit;
    }

    // Блокируем
    if (!flock($file, LOCK_EX)) {
        echo json_encode(['error' => 'Failed to lock spam file']);
        fclose($file);
        exit;
    }

    // Записываем номера в файл
    foreach ($spamNumbers as $number) {
        fwrite($file, $number . "\n");
    }

    // Разблокируем
    flock($file, LOCK_UN);
    fclose($file);

    echo json_encode(['status' => 'success', 'message' => 'Phone number added']);
}

// Функция для приведения номера к формату "+7xxxxxxxxxx"
function normalizeNumber($number) {
    $number = preg_replace('/\s+/', '', $number);
    if (strpos($number, '8') === 0) {
        $number = '+7' . substr($number, 1);
    }
    return $number;
}

// Функция для валидирования номера
function isValidPhoneNumber($number) {
    return preg_match('/^\+7\d{10}$/', $number);
}

// Функция бинарного поиска
function binarySearch($array, $target) {
    $left = 0;
    $right = count($array) - 1;

    while ($left <= $right) {
        $mid = (int)(($left + $right) / 2);

        if ($array[$mid] === $target) {
            return true; // Found the target
        }

        if ($array[$mid] < $target) {
            $left = $mid + 1;
        } else {
            $right = $mid - 1;
        }
    }

    return false; // Target not found
}
?>
