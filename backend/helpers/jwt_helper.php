<?php
// Classe utilitária reservada para futura implementação de JSON Web Tokens (JWT)
class JwtHelper {
    public static function gerarToken($dados) {
        return base64_encode(json_encode($dados));
    }
}
?>