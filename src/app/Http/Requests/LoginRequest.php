<?php

namespace App\Kitchen\app\Http\Requests;

class LoginRequest {

    public static function validateLogin($data) {
        $errors = [];

        if (empty($data['shop_id'])) {
            $errors['shop_id'] = 'error_shop_required';
        }

        if (empty($data['login'])) {
            $errors['login'] = 'error_login_required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'error_password_required';
        }

        return $errors;
    }

}