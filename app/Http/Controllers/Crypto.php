<?php

namespace App\Http\Controllers;

class Crypto extends Controller
{
    public function decrypt()
    {
        $input = file_get_contents('php://input');
        $post = json_decode($input);
        $key = hex2bin($post->key);
        $iv = hex2bin($post->iv);
        $encrypted = $post->encrypted;
        try {
            //$key = hex2bin('0123456789abcdef0123456789abcdef');
            //$iv = hex2bin('abcdef9876543210abcdef9876543210');
            // we receive the encrypted string from the post
            //$encrypted = $_POST['decrypt'];
            $decrypted = openssl_decrypt($encrypted, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
            // finally we trim to get our original string
            $decrypted = trim($decrypted);
            //dd($decrypted);

            return response(['decrypted' => $decrypted])->header('Content-Type', 'application/json');
        } catch (Exception $e) {
            return response(['error' => $e->getMessage()])->header('Content-Type', 'application/json');
        }
    }
}

