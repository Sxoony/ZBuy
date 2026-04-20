<?php   

// Sanitization funcs
function sanitize_string(string $data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function sanitize_int(int $inty){
return (int) filter_var($inty, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_float(mixed $value) : float{
return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}


function timeAgo($date){
 // Input date (MySQL format: YYYY-MM-DD HH:MM:SS)
    $inputDate = new DateTime($date);
    
    // Current date/time
    $now = new DateTime();

    // Difference in seconds
    $diffSeconds = abs($now->getTimestamp() - $inputDate->getTimestamp());
    
    // Convert to minutes
    $minutes = floor($diffSeconds / 60);

    if ($minutes < 60) {
        return $minutes . " minute(s) ago";
    }

    // Convert to hours
    $hours = floor($minutes / 60);

    if ($hours < 24) {
        return $hours . " hour(s) ago";
    }

    // Convert to days
    $days = floor($hours / 24);

    return $days . " day(s) ago";

}
//Validation funcs
function notEmptyValue($value) : bool{
return trim($value) !== '';
}

function isLengthBetween(string $value, int $min, int $max):bool{
$length = strlen($value);
return (($length >=$min) && ($length <=$max));
}

function validate_email(string $email) : bool{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateNonNegInt(int $value) : bool{
return filter_var($value,FILTER_VALIDATE_INT) !== false && (int) $value>=0;
}

function isAllowedValue(string $value, array $allowedValues) : bool{
return in_array($value, $allowedValues, true);
}


function formatPrice(float $price, string $currency ='R') : string{
return $currency . ' ' . number_format($price,2);
}

function formatDate(string $datetime):string{
    return (new DateTime($datetime))->format('d M Y'); //for viewing, not internal storage.
}



//HTTP response helpers
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? sanitize_string($_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? sanitize_string($_GET[$key]) : $default;
}
?>