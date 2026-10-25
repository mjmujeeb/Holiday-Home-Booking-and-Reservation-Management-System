<?php
/** Read and validate the add/edit home form. @return array{0: array, 1: string[]} */
function read_home_form(): array
{
    $d = [
        'name'        => post_str('name', 120),
        'location'    => post_str('location', 120),
        'description' => post_text('description', 3000),
        'status'      => post_str('status', 15),
    ];
    $rating = $_POST['rating'] ?? '';
    $price  = $_POST['price'] ?? '';
    $errors = [];

    if (mb_strlen($d['name']) < 3)        { $errors[] = 'Give the home a name (at least 3 characters).'; }
    if (mb_strlen($d['location']) < 2)    { $errors[] = 'Enter a location.'; }
    if (mb_strlen($d['description']) < 20) { $errors[] = 'The description needs at least 20 characters.'; }
    if (!in_array($d['status'], ['available', 'not_available'], true)) { $errors[] = 'Choose a listing status.'; }

    if (!is_string($rating) || !is_numeric($rating) || $rating < 0 || $rating > 5) {
        $errors[] = 'Rating must be a number from 0 to 5.';
    }
    if (!is_string($price) || !is_numeric($price) || $price < 1 || $price > 1000000) {
        $errors[] = 'Price per night must be between 1 and 1,000,000.';
    }
    $d['rating'] = number_format(is_numeric($rating) ? min(5, max(0, (float) $rating)) : 0, 1, '.', '');
    $d['price']  = number_format(is_numeric($price) ? min(1000000, max(1, (float) $price)) : 1, 2, '.', '');
    return [$d, $errors];
}
