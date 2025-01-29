<?php
// Define the secret token and spam numbers file
define('SECRET_TOKEN', 'MY_SECRET_TOKEN');
define('SPAM_FILE', 'spam_numbers.txt');

// Check if action is set
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

// Function to handle default endpoint (check phone number)
function handleDefaultRequest() {
    // Read the spam numbers file
    if (file_exists(SPAM_FILE)) {
        $spamNumbers = file(SPAM_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        sort($spamNumbers);
    } else {
        $spamNumbers = array();
    }

    // Read input from the user via HTTP POST or GET
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = trim($_POST['phone_number']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = trim($_GET['phone_number']);
    } else {
        echo json_encode(["error" => "Invalid request method"]);
        exit;
    }

    // Normalize the input to the format "+7xxxxxxxxxx"
    $normalizedInput = normalizeNumber($input);

    // Perform binary search to check if the number is spam
    if (binarySearch($spamNumbers, $normalizedInput)) {
        echo json_encode(["status" => "spam"]);
    } else {
        echo json_encode(["status" => "normal"]);
    }
}

// Function to handle add endpoint
function handleAddRequest() {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit;
    }

    // Check for token
    if (!isset($_POST['token']) || $_POST['token'] !== SECRET_TOKEN) {
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    // Get phone number
    if (!isset($_POST['phone_number'])) {
        echo json_encode(['error' => 'Phone number not provided']);
        exit;
    }

    $input = trim($_POST['phone_number']);

    // Normalize the phone number
    $normalizedNumber = normalizeNumber($input);

    // Validate the phone number
    if (!isValidPhoneNumber($normalizedNumber)) {
        echo json_encode(['error' => 'Invalid phone number']);
        exit;
    }

    // Read existing spam numbers
    if (!file_exists(SPAM_FILE)) {
        // Create the file if it doesn't exist
        if (!touch(SPAM_FILE)) {
            echo json_encode(['error' => 'Failed to create spam file']);
            exit;
        }
    }

    $spamNumbers = file(SPAM_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Check if the number already exists
    if (binarySearch($spamNumbers, $normalizedNumber)) {
        echo json_encode(['error' => 'Phone number already exists']);
        exit;
    }

    // Add the new number to the array
    $spamNumbers[] = $normalizedNumber;

    // Sort the array
    sort($spamNumbers);

    // Write the array back to the file
    $file = fopen(SPAM_FILE, 'w');
    if ($file === false) {
        echo json_encode(['error' => 'Failed to open spam file for writing']);
        exit;
    }

    // Lock the file
    if (!flock($file, LOCK_EX)) {
        echo json_encode(['error' => 'Failed to lock spam file']);
        fclose($file);
        exit;
    }

    // Write each number to the file
    foreach ($spamNumbers as $number) {
        fwrite($file, $number . "\n");
    }

    // Unlock and close the file
    flock($file, LOCK_UN);
    fclose($file);

    echo json_encode(['status' => 'success', 'message' => 'Phone number added']);
}

// Function to normalize input to "+7xxxxxxxxxx" format
function normalizeNumber($number) {
    $number = preg_replace('/\s+/', '', $number);
    if (strpos($number, '8') === 0) {
        $number = '+7' . substr($number, 1);
    }
    return $number;
}

// Function to validate phone number
function isValidPhoneNumber($number) {
    return preg_match('/^\+7\d{10}$/', $number);
}

// Binary search function
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