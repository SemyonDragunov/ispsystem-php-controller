<?php

// Необходимо для манипуляций.
require_once(__DIR__ . '/apiarray.inc');

/**
 * Отправка запросов к сервису.
 */
class IspRequest
{
  protected $path = ''; // Путь до сайта с ISP.
  protected $lang = 'ru';
  protected $service; // Имя сервиса для подключения.
  protected $request_array; // Массив с параметрами.

  private $_out = 'sjson';
  private $_admin_login = ''; // Логин админа.
  private $_admin_pass = ''; // Пароль админа.

  /**
   * Устанавливает авторизацию.
   *
   * @param $auth
   *  'admin' - Берет аккаунт админа.
   *  'user' - Необходимо указать логин и пароль.
   * @param null $login
   * @param null $password
   * @return $this
   */
  final public function auth($auth, $login = NULL, $password = NULL) {
    $param = array('authinfo' => '');

    if ('admin' == $auth) {
      $param['authinfo'] = $this->_admin_login . ':' . $this->_admin_pass;
    }
    if ('user' == $auth) {
      if (!is_null($login) && !is_null($password)) {
        $param['authinfo'] = $login . ':' . $password;
      }
      else {
        return FALSE;
      }
    }

    return $this->request($param);
  }

  /**
   * Вызвать запрос от имени другого пользователя.
   * Важно от кого производиться сам вызов. Есть ограничения на использование.
   * @param $name
   *  Имя пользователя аккаунта. Обычно это e-mail.
   * @return $this
   */
  final public function su($name) {
    return $this->request(array('su' => $name));
  }

  /**
   * Добавляет параметры в запрос.
   * Перезаписывает уже существующие.
   *
   * @param array $param
   * @param null $func
   * @return $this
   */
  final public function request(array $param, $func = NULL) {
    // Существует ли отдельно название функции.
    if (!is_null($func)) {
      $param_all = array_merge(array('func' => $func), $param);
    }
    else {
      $param_all = $param;
    }

    // Если до этого уже были параметры.
    if (!empty($this->request_array)) {
      $param_all = array_merge($this->request_array, $param_all);
    }

    $this->request_array = $param_all;

    return $this;
  }

  /**
   * Подготовка запроса.
   */
  final private function _prepareRequest() {
    if (!$this->_checkRequest()) return FALSE;

    $str = $this->path . '/' . $this->service;
    $str .= '?out=' . $this->_out;
    $str .= '&lang=' . $this->lang;
    $str .= '&' . http_build_query($this->request_array);

    return $str;
  }

  /**
   * Проверка массива запроса.
   */
  final private function _checkRequest() {
    if (!empty($this->request_array)) {
      $this->request_array = ApiArray::removeEmptyElements($this->request_array);

      if (!empty($this->request_array)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Проверяет успешность результата ответа.
   */
  protected function checkAnswer($data) {
    if (isset($data['doc']['error'])) {
      return FALSE;
    }

    // Если была операция на создание или изменение.
    if (isset($this->request_array['sok'])) {
      if (!isset($data['doc']['ok'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Подготовка ответа с сервиса к дальнейшей работе.
   * Удаление ненужных символов.
   */
  protected final function prepareAnswer(array &$array)
  {
    $i = 0;
    foreach ($array as $key => &$value) {
      // Переписывает символ $.
      if (preg_match('/\$/', $key)) {
        $new_key = preg_replace('/\$/', '', (string) $key);

        $v = isset($array[$i]) ? $i + 777 : $i;
        if (empty($new_key)) $new_key = $v;

        $array[$new_key] = $value;
        unset($array[$key]);
      }

      // Если ключ пустой.
      if ($key == '' || empty($key)) {
        $v = isset($array[$i]) ? $i + 777 : $i;
        if (empty($new_key)) $new_key = $v;

        $array[$new_key] = $value;
        unset($array[$key]);
      }

      if (is_array($value)) $this->prepareAnswer($value);
      $i++;
    }
  }

  /**
   * Смена адреса отправки запроса.
   * @param $path
   *  Домен c http/https, без доп. деректив и закрывающего слеша.
   * @return $this
   */
  final public function path($path) {
    $this->path = $path;
    return $this;
  }

  /**
   * Смена продукта в который поступит запрос.
   * @param $service
   *  ispmgr, billmgr, vmmgr, vemgr, dcimgr, dnsmgr, ipmgr, core & custom.
   * @return $this
   */
  final public function service($service) {
    $this->service = $service;
    return $this;
  }

  /**
   * Устанавливает язык вывода.
   * @param $lang
   *  ru (default), en.
   * @return $this
   */
  final public function lang($lang) {
    $this->lang = $lang;
    return $this;
  }

  /**
   * Отправка подготовленного запроса.
   */
  final public function send() {
    if ($str = $this->_prepareRequest()) {
      $json = file_get_contents($str);
      $data = json_decode($json);
    }
    else {
      return FALSE;
    }

    $data = (array) ApiArray::objectToArray($data);
    $this->prepareAnswer($data);

    // Проверка ответа.
    if (!$this->checkAnswer($data)) {
      return FALSE;
    }

    return $data;
  }
}

/**
 * Трайт с функциями операций.
 */
trait IspOperations
{
  /**
   * Подготовка данных к запросу для действий над сущностью.
   * @param $func
   * @param $op
   *  add, edit, get.
   * @param array $param
   * @return array|bool
   */
  protected function actionOperation($func, $op, array $param)
  {
    $param_all = array('func' => $func);
    if ('add' == $op) {
      $param_all = array_merge(array('sok' => 'ok'), $param_all, $param);
    }
    elseif ('edit' == $op) {
      $param_all = array_merge(array('sok' => 'ok'), $param_all, $param);
    }
    elseif ('get' == $op) {
      $param_all = array_merge($param_all, $param);
    }
    else {
      return FALSE;
    }

    return $param_all;
  }
}

/**
 * Работа с аккаунтами в BILLmanager.
 */
class IspBillAccount extends IspRequest
{
  use IspOperations;

  public $service = 'billmgr';

  /**
   * Подготовка параметров для действия над аккаунтом по ID.
   * @param $op
   *  get - получить данные аккаунта.
   *  add - создать аккаунт.
   *  edit - изменить аккаунт.
   * @param array $param
   * @param null $elid
   *  ID клиента, необходим для редактирования.
   * @return $this|bool
   */
  final public function account($op, array $param, $elid = NULL)
  {
    // Edit and Get.
    if (('edit' == $op || 'get' == $op) && is_null($elid)) return FALSE;
    if (('edit' == $op || 'get' == $op) && !is_null($elid))
      $param = array_merge(array('elid' => $elid), $param);

    // Включаем оповещение о создании пользователя.
    if ('add' == $op) $param = array_merge(array('notify' => 'on'), $param);

    $this->request_array = $this->actionOperation('account.edit', $op, $param);

    return $this;
  }

  /**
   * Получение данных о аккаунта по email.
   * @param $email
   * @return array|bool
   */
  final public function account_get_by_email($email) {
    $param = array(
      'filter' => 'on',
      'email' => $email,
    );
    $answer = $this->request($param, 'user')->auth('admin')->send();

    if (!isset($answer['doc']['elem'])) return FALSE;

    $accounts = array();
    $i = 0;
    foreach($answer['doc']['elem'] as $account) {
      $accounts[$i] = $account;
      $i++;
    }

    return $accounts;
  }
}

/**
 * Работа с профилями плательщиков в BILLmanager.
 */
class IspBillProfile extends IspBillAccount
{
  use IspOperations;

  public $service = 'billmgr';

  /**
   * Подготовка параметров для действия над профилем (плательщик).
   * @param $op
   *  edit - правка профиля.
   *  get - получить данные профиль.
   * @param array $param
   * @param null $elid
   * @return $this|bool
   */
  final public function profile($op, array $param, $elid = NULL)
  {
    // Edit and Get.
    if (('edit' == $op || 'get' == $op) && is_null($elid)) return FALSE;
    if (('edit' == $op || 'get' == $op) && !is_null($elid)) {
      $param = array_merge(array('elid' => $elid), $param);
      $func = 'profile.edit';
    }

    // Add.
    if ('add' == $op) $func = 'profile.add.profiledata';

    $this->request_array = $this->actionOperation($func, $op, $param);

    return $this;
  }
}

/**
 * Операции с авторизацией.
 * Все функции вызываемые. Без необходимости send().
 */
class IspAuth extends IspRequest
{
  /**
   * Создать новую сессию и получить её параметры.
   * @param $login
   * @param $password
   * @return bool
   */
  public final function newSession($login, $password)
  {
    return $this->request(array(
      'username' => $login,
      'password' => $password,
    ), 'auth')
      ->auth('admin')
      ->send();
  }

  /**
   * Совершить редирект на нужную форму с помощью Auth ID.
   * @param $auth_id
   * @param $form
   *  Название формы редиректа.
   */
  public final function redirect($auth_id, $form)
  {
    $url = $this->path . '/' . $this->service . "?auth={$auth_id}&startform={$form}";

    header("Location: " . $url);
    die();
  }

  /**
   * Сквозная авторизация по ключу с редиректом.
   * @param $login
   * @param null $form
   *  Название формы редиректа. Или по умолчанию.
   * @return bool
   */
  public final function authConnect($login, $form = NULL)
  {
    // Generate auth key.
    $key = 'abcdefghijklmnoprstyuwz1234567890';
    $key = str_shuffle($key);
    $key = mb_strimwidth($key, 4, 16);

    $param = array(
      'username' => $login,
      'key' => $key,
    );

    if ($this->request($param, 'session.newkey')->auth('admin')->send()) {
      $redirect = !is_null($form) ? '&redirect=' . urlencode('startform=' . $form) : '';
      $url = $this->path . '/' . $this->service . "?func=auth&username={$login}&key={$key}&checkcookie=no{$redirect}";

      header("Location: " . $url);
      die();
    }
  }
}