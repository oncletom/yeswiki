<?php
namespace YesWiki;
require_once('includes/User.php');
require_once('includes/EncryptedPassword.php');
require_once('includes/ClearPassword.php');

class UserFactory
{
    private $database;
    private $cookies;

    public function __construct($database, $cookies = null)
    {
        $this->database = $database;
        $this->cookies = $cookies;
    }

    /**
     * Créer un utilisateur si il existe dans la base de donnée. Renvoie Faux si
     * il n'existe pas.
     * @param  string $name Nom de l'utilisateur
     * @return User|false
     */
    public function get($name)
    {
        $table = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);

        $userInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE name = '$name' LIMIT 1"
        );

        if (empty($userInfos)) {
            return false;
        }

        $userInfos['password'] = new EncryptedPassword($userInfos['password']);
        return new User($this->database, $userInfos);
    }

    /**
     * Créé  l'objet User pour l'utilisateur connecté. Si aucune utilisateur
     * n'est connecté renvoie null.
     * @return User|null
     */
    public function getConnected()
    {
        // If cookies not initialised, no connected user.
        if (is_null($this->cookies)) {
            return null;
        }

        if ($this->cookies->isset('name') and isset($_SESSION['user'])) {
            $user = $this->get($this->cookies->get('name'));
            // User doesn't exist
            if (!$user) {
                return null;
            }
            // bad password.
            $password = new EncryptedPassword($this->cookies->get('password'));
            if (!$user->password->isMatching($password)) {
                return null;
            }
            return $user;
        }
        return null;
    }

    /**
     * Vérifie si un utilisateur existe.
     * @param  string  $name Nom de l'utilisateur
     * @return boolean vrai si l'utilisateur existe sinon faux
     */
    public function isExist($name)
    {
        $table = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);

        $userInfos = $this->database->loadSingle(
            "SELECT * FROM $table WHERE name = '$name' LIMIT 1"
        );

        if (empty($userInfos)) {
            return false;
        }

        return true;
    }

    /**
     * Créé un objet User pour chaque utilisateur présent dans la base de données
     * @return array Tableau d'objets User
     */
    public function getAll()
    {
        $tableUsers = $this->database->prefix . 'users';
        $usersInfos = $this->database->loadAll(
            "SELECT * FROM $tableUsers ORDER BY name"
        );

        $users = array();
        foreach ($usersInfos as $userInfos) {
            $userInfos['password'] = new EncryptedPassword($userInfos['password']);
            $users[$userInfos['name']] = new User($this->database, $userInfos);
        }

        return $users;
    }

    /**
     * Créé un nouvel utilisateur
     * @param  string $name     Nom de l'utilisateur (le fait que ce soit un
     *                          WikiName doit deja etre vérifié)
     * @param  string $email    Email de l'utilisateur
     * @param  string $password Mot de passe de l'utilisateur
     * @return User           [description]
     */
    public function new($name, $email, $password)
    {
        $tableUsers = $this->database->prefix . 'users';
        $name = $this->database->escapeString($name);
        $email = $this->database->escapeString($email);
        $password = $this->database->escapeString((string)$password);

        $sql = "INSERT INTO $tableUsers
                SET signuptime = now(),
                    name = '$name',
                    email = '$email',
                    password = md5('$password')";

        $this->database->query($sql);

        return $this->get($name);
    }
}