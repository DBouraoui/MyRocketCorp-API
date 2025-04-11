<?php

namespace App\DTO\user;

use App\traits\User;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegisterDTO
{
    use User;

    #[NotBlank(message: "L'email ne peut pas être vide.")]
    public ?string $email;

    #[NotBlank(message: "Le mot de passe ne peut pas être vide.")]
    public ?string $password;

    #[NotBlank(message: "Le code postal ne peut pas être vide.")]
    public ?string $postCode;

    #[NotBlank(message: "La ville ne peut pas être vide.")]
    public ?string $city;

    #[NotBlank(message: "Le pays ne peut pas être vide.")]
    public ?string $country;

    #[NotBlank(message: "Le numéro de téléphone ne peut pas être vide.")]
    public ?string $phone;

    #[NotBlank(message: "L'adresse ne peut pas être vide.")]
    public ?string $address;

}