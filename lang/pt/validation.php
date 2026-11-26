<?php

// Mensagens de validação do framework, em português, só as das regras que o
// Nexo ID usa; qualquer outra regra cai no inglês do framework (H-nexoid-12).
return [
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha não está correta.',
    'email' => 'Digite um e-mail válido.',
    'max' => [
        'string' => ':Attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'string' => ':Attribute precisa ter pelo menos :min caracteres.',
    ],
    'required' => 'Preencha :attribute.',
    'string' => ':Attribute precisa ser um texto.',
    'unique' => 'Já existe uma conta com :attribute.',

    'attributes' => [
        'display_name' => 'o nome de exibição',
        'email' => 'o e-mail',
        'password' => 'a senha',
        'current_password' => 'a senha atual',
        'delete_password' => 'sua senha',
    ],
];
