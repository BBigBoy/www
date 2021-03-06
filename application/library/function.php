<?php
/**
 * 生成本域名下访问的链接地址
 * @param $url string 组成的格式为'/moduleName/controllerName/actionName/'
 * @param $getParam array
 * @param bool $host
 * @return string
 */
function U($url, $getParam = array(), $host = true)
{
    $url = (strpos($url, '/') == 0) ? $url : ('/' . $url);
    return ($host ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/index.php' . $url . arrToGetParamStr($getParam);
}

/**
 * 获取配置参数
 * @param $name string 配置名称 如果为null,则默认返回该分节所有配置
 * 默认为conf目录下modules.ini文件下platform节中对应的配置
 * @param $section string ini文件中分节的名称
 * @param $fileName string ini文件名称
 * @return mixed|void
 * 如果$section或者$fileName非法，返回bool值false
 */
function C($name = null, $section = 'platform', $fileName = 'modules.ini')
{
    if ($section == 'platform' && $fileName == 'modules.ini') {
        $section = (Yaf_Dispatcher::getInstance()->getRequest()->getModuleName()) ?: $section;
    }
    try {
        $config = new Yaf_Config_Ini(APPLICATION_PATH . '/conf/' . $fileName);
        if (isset($config[$section])) {
            $config = $config[$section];
        } else if ($fileName == 'modules.ini') {
            $config = $config['platform'];
        } else {
            return false;
        }
    } catch (Exception $e) {
        //記錄一下錯誤日誌
        return false;
    }
    if (is_null($name)) {
        return $config;
    }
    $arr = explode('.', $name);
    if (is_array($arr)) {
        foreach ($arr as $item) {
            $config = $config[$item];
        }
        return $config;
    } else {
        return $config[$name];
    }
}

/**
 * session的value值只允许string，int，
 * 不允许array，本方法以此判断是否为设置值或者取值，
 * 如果为array，且为空数组，则直接返回已有的值，
 * 如果为数组且不为空，则抛出异常。
 * @param $name
 * @param array $value
 * @return bool|mixed|Yaf_Session
 * @throws \Yaf\Exception
 */
function session($name, $value = array())
{
    $sessionObj = Yaf_Session::getInstance();
    if (is_string($value) || is_numeric($value)) {
        return $sessionObj->set($name, $value);
    } else if (is_array($value) && !$value) {
        return $sessionObj->get($name);
    }
    throw new \Yaf\Exception('Invalid session value!');
}

/**
 * Cookie 设置、获取、删除
 * @param string $name cookie名称
 * @param mixed $value cookie值
 * @param mixed $option cookie参数
 * @return mixed
 */
function cookie($name = '', $value = '', $option = null)
{
    // 默认设置
    $config = array(
        'prefix' => '', // cookie 名称前缀
        'expire' => 0, // cookie 保存时间
        'path' => '/', // cookie 保存路径
        'domain' => '', // cookie 有效域名
        'secure' => false, //  cookie 启用安全传输
        'httponly' => '', // httponly设置
    );
    // 参数设置(会覆盖黙认设置)
    if (!is_null($option)) {
        if (is_numeric($option))
            $option = array('expire' => $option);
        elseif (is_string($option))
            parse_str($option, $option);
        $config = array_merge($config, array_change_key_case($option));
    }
    if (!empty($config['httponly'])) {
        ini_set("session.cookie_httponly", 1);
    }
    // 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE))
            return null;
        // 要删除的cookie前缀，不指定则删除config设置的指定前缀
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) {// 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                if (0 === stripos($key, $prefix)) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                    unset($_COOKIE[$key]);
                }
            }
        }
        return null;
    } elseif ('' === $name) {
        // 获取全部的cookie
        return $_COOKIE;
    }
    $name = $config['prefix'] . str_replace('.', '_', $name);
    if ('' === $value) {
        if (isset($_COOKIE[$name])) {
            $value = $_COOKIE[$name];
            if (0 === strpos($value, 'think:')) {
                $value = substr($value, 6);
                return array_map('urldecode', json_decode($value, true));
            } else {
                return $value;
            }
        } else {
            return null;
        }
    } else {
        if (is_null($value)) {
            setcookie($name, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {
            // 设置cookie
            if (is_array($value)) {
                $value = 'think:' . json_encode(array_map('urlencode', $value));
            }
            $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
            setcookie($name, $value, $expire, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            $_COOKIE[$name] = $value;
        }
    }
    return null;
}

/**
 * 缓存管理
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value 缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 */
function S($name, $value = '', $options = null)
{
    static $cache = '';
    if (is_array($options)) {
        // 缓存操作的同时初始化
        $type = isset($options['type']) ? $options['type'] : '';
        $cache = Cache_Base::getInstance($type, $options);
    } elseif (is_array($name)) { // 缓存初始化
        $type = isset($name['type']) ? $name['type'] : '';
        $cache = Cache_Base::getInstance($type, $name);
        return $cache;
    } elseif (empty($cache)) { // 自动初始化
        $cache = Cache_Base::getInstance();
    }
    if ('' === $value) { // 获取缓存
        return $cache->get($name);
    } elseif (is_null($value)) { // 删除缓存
        return $cache->rm($name);
    } else { // 缓存数据
        if (is_array($options)) {
            $expire = isset($options['expire']) ? $options['expire'] : NULL;
        } else {
            $expire = is_numeric($options) ? $options : NULL;
        }
        return $cache->set($name, $value, $expire);
    }
}

/**
 * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
 * @param string $name 缓存名称
 * @param mixed $value 缓存值
 * @return mixed
 */
function F($name, $value = '')
{
    /** @var string $path 缓存路径 */
    $path = (new Yaf_Config_Ini(APPLICATION_PATH . '/conf/cache.ini', 'cache'))
        ->get('cache')->get('path');
    static $_cache = array();
    $filename = $path . $name . '.php';
    if ('' !== $value) {
        if (is_null($value)) {
            // 删除缓存
            if (false !== strpos($name, '*')) {
                return false;
            } else {
                unset($_cache[$name]);
                return Storage_Base::unlink($filename, 'F');
            }
        } else {
            Storage_Base::put($filename, serialize($value), 'F');
            // 缓存数据
            $_cache[$name] = $value;
            return null;
        }
    }
    // 获取缓存数据
    if (isset($_cache[$name]))
        return $_cache[$name];
    if (Storage_Base::has($filename, 'F')) {
        $value = unserialize(Storage_Base::read($filename, 'F'));
        $_cache[$name] = $value;
    } else {
        $value = false;
    }
    return $value;
}

/**
 * http请求，根据$data是否为空，自动进行get或post操作
 * @param $url
 * @param null $data
 * @param int $curl_time 允许curl函数执行的时间，控制它可以实现异步http请求
 * @return mixed
 */
function http_request($url, $data = null, $curl_time = 0)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if ($data != null) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    if ($curl_time > 0) {
        curl_setopt($curl, CURLOPT_NOSIGNAL, 1);    //注意，毫秒超时一定要设置这个
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, $curl_time);  //超时毫秒，cURL 7.16.2中被加入。从PHP 5.2.3起可使用
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($curl);
    if (curl_errno($curl)) {//出错则显示错误信息
        print curl_error($curl);
    }
    curl_close($curl);
    return $output;
}

/**
 * 异步http请求，不等待返回结果
 * 根据$data是否为空，自动进行get或post操作
 * @param $url
 * @param null $data
 * @return mixed
 */
function async_http_request_no_result($url, $data = null)
{
    http_request($url, $data, 1);
}

/**
 * 向微信服务器请求数据，并处理返回结果
 *
 * @param $post_url string 具体请求的API地址
 * @param $data mixed 向微信服务器提交的数据,可以是json形式的，也可以是数组
 * @param $is_file bool 标识当前调用是否为文件上传，默认为false
 * @return bool|mixed  为NULL或者错误代码，则返回false。  若为有效值则直接返回结果数组。
 */
function requestWXServer($post_url, $data = null, $is_file = false)
{
    //当第一次请求失败时，递归请求一次，仍然失败则返回false
    static $requestTimes = 0;
    if (is_array($data) && !$is_file)
        $data = decodeUnicodeToUTF8(json_encode($data));
    $wxResponseStr = http_request($post_url, $data);
    if ($wxResponseStr) {
        $wxResponseAtt = json_decode($wxResponseStr, true);
        //(__METHOD__, __LINE__, json_encode($wxResponseAtt), $wxResponseAtt['errcode']);
        if (!$wxResponseAtt || (((int)($wxResponseAtt['errcode'])) !== 0)) {
            if ($requestTimes === 0 && (((int)($wxResponseAtt['errcode'])) == -1)) {
                errorLog('$requestTimes--->' . $requestTimes . '---posturl---->' . $post_url . '---postdata---->' . json_encode($data) . '---wxResponseStr---->' . $wxResponseStr, $wxResponseAtt['errcode']);
                $requestTimes++;
                requestWXServer($post_url, $data);
            } else {
                if ($wxResponseAtt['errcode'] != 9001003)
                    errorLog('$requestTimes--->' . $requestTimes . '---posturl---->' . $post_url . '---postdata---->' . json_encode($data) . '---wxResponseStr---->' . $wxResponseStr, $wxResponseAtt['errcode']);
                return false;
            }
        }
        return $wxResponseAtt;
    }
    //第一次请求出错，则再请求一次，如果仍然出错，则返回false
    if ($requestTimes === 0) {
        errorLog('$requestTimes--->' . $requestTimes . '---posturl---->' . $post_url . '---postdata---->' . json_encode($data));
        $requestTimes++;
        requestWXServer($post_url, $data);
    } else {
        errorLog('$requestTimes--->' . $requestTimes . '---posturl---->' . $post_url . '---postdata---->' . json_encode($data));
        return false;
    }
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 * @param mixed $mix 变量
 * @return string
 */
function to_guid_string($mix)
{
    if (is_object($mix)) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * 将unicode格式的字符串转换为UTF-8
 * @param $str
 * @return mixed
 */
function decodeUnicodeToUTF8($str)
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i',
        create_function(
            '$matches',
            'return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");'
        ),
        $str);
}

/**
 * 统一获取参数，get、post等
 * @param $paramName string
 * @return mixed
 */
function getParam($paramName)
{
    $arr = explode('.', $paramName);
    $request = Yaf_Dispatcher::getInstance()->getRequest();
    if ($arr[0] == 'post') {
        return $arr[1] ? $request->getPost($arr[1]) : $request->getPost();
    } elseif ($arr[0] == 'get') {
        return ($arr[1] ? $request->getQuery($arr[1]) : $request->getQuery()) ?: $request->getParam($getName);
    } else {
        return $request->get($paramName);
    }
}

/**
 * 记录程序错误信息
 * @param string $addMsg 附加的错误信息
 * @param int $errorCode 错误编号
 * @param bool|false $mail 是否发送邮件提醒
 */
function errorLog($addMsg = '', $errorCode = -3, $mail = false)
{
    $errorModel = new LogModel();
    $errorInfo['errBatch'] = time();
    $request = Yaf_Application::app()->getDispatcher()->getRequest();
    $errorInfo['module'] = $request->getModuleName();
    $errorInfo['controller'] = $request->getControllerName();
    $errorInfo['action'] = $request->getActionName();
    $debugBacktrace = debug_backtrace();
    $invokeMethodInfo = $debugBacktrace[1];
    $errorInfo['method'] = $invokeMethodInfo['class'] . '::' . $invokeMethodInfo['function'];
    $errorInfo['line'] = $debugBacktrace[0]['line'];
    $errorInfo['errcode'] = $errorCode ? $errorCode : -3;
    $errorInfo['errmsg'] = $addMsg;
    $requestContent = 'clientIP:' . get_client_ip() . '--->post参数：' . decodeUnicodeToUTF8(json_encode(getParam('post.'))) . '<--->get参数:' . $_SERVER['REQUEST_URI'];
    $errorInfo['requestContent'] = stripslashes($requestContent);
    $errorInfo['backtrace'] = stripslashes(decodeUnicodeToUTF8(json_encode(debug_backtrace())));
    $errorModel->addLog($errorInfo);
    if ($mail) {
        $errMsg = json_encode($errorInfo);
        sendMail(stripslashes(decodeUnicodeToUTF8($errMsg)), '错误提示');
    }
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false)
{
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) return $ip[$type];
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 压缩html : 清除换行符,清除制表符,去掉注释标记
 * @param $string
 * @return string 压缩后的$string
 * */
function compress_html($string)
{
    $string = str_replace("\r\n", '', $string); //清除换行符
    $string = str_replace("\n", '', $string); //清除换行符
    $string = str_replace("\t", '', $string); //清除制表符
    $pattern = array(
        "/> *([^ ]*) *</", //去掉注释标记
        "/[\s]+/",
        "/<!--[^!]*-->/",
        "/\" /",
        "/ \"/",
        "'/\*[^*]*\*/'"
    );
    $replace = array(
        ">\\1<",
        " ",
        "",
        "\"",
        "\"",
        ""
    );
    return preg_replace($pattern, $replace, $string);

}

/**
 * 获得字符串长度，英文字符长为1，中文字符长度为2
 * @param $string string 待计算长度的字符串
 * @return int 字符串长度
 */
function stringLength($string)
{
    $string = (string)$string;
    return (strlen($string) + mb_strlen($string, 'utf-8')) / 2;
}

/**
 * 将数组转换为url的get参数形式的字符串
 * @param $arr array 需要转换的数组
 * @param bool $questionOrAndMark 前面添加'?'号或者'&'号
 * @return string 返回拼接后的字符串
 */
function arrToGetParamStr($arr, $questionOrAndMark = true)
{

    $getString = $questionOrAndMark ? '?' : '&';
    foreach ($arr as $key => &$value) {
        $value = ($key . '=' . $value);
    }
    $getString .= implode('&', $arr);
    return $getString;
}

/**
 * 获得当前访问网站的移动设备类型
 * @return string
 */
function getMobileInfo()
{
    $useragent = strtolower($_SERVER["HTTP_USER_AGENT"]);
    // iphone
    $is_iphone = strripos($useragent, 'iphone');
    if ($is_iphone) {
        return 'iphone';
    }
    // android
    $is_android = strripos($useragent, 'android');
    if ($is_android) {
        return 'android';
    }
    // 微信
    /*$is_weixin = strripos($useragent, 'micromessenger');
    if ($is_weixin) {
        return 'weixin';
    }*/
    // ipad
    $is_ipad = strripos($useragent, 'ipad');
    if ($is_ipad) {
        return 'ipad';
    }
    // ipod
    $is_ipod = strripos($useragent, 'ipod');
    if ($is_ipod) {
        return 'ipod';
    }
    //windows phone
    $is_windows = strripos($useragent, 'windows phone');
    if ($is_windows) {
        return 'windows phone';
    }
    // pc电脑
    /*$is_pc = strripos($useragent, 'windows nt');
    if ($is_pc) {
        return 'pc';
    }*/
    return 'other';
}

/**
 * 发送邮件通知
 * @param $content string 邮件内容
 * @param null $subject 邮件主题.如果为空,则替换为内容
 */
function sendMail($content, $subject = null)
{
    Yaf_Loader::import(APPLICATION_PATH . '/application/library/Swift/swift_required.php');
    $mailConfig = C(null, 'mail', 'mail.ini');
    $transport = \Swift_SmtpTransport::newInstance($mailConfig['HOST'],
        $mailConfig['PORT'], $mailConfig['SECURITY'])
        ->setUsername($mailConfig['USER'])
        ->setPassword($mailConfig['PWD']);
    $mailer = \Swift_Mailer::newInstance($transport);
    $message = \Swift_Message::newInstance()
        ->setSubject(is_string($subject) ? $subject : $content)
        ->setFrom(array($mailConfig['FROM_MAIL'] => $mailConfig['FROM_MAIL_NAME']))
        ->setTo($mailConfig['TO_MAIL'])
        ->setContentType("text/html")
        ->setBody($content);
    $mailer->protocol = 'smtp';
    $mailer->send($message);
}

/**
 * 添加任务队列,当前仅支持新浪云SaeTaskQueue,被添加的任务将会顺序执行
 * TODO:在非sae环境类任务队列的实现
 * @param $taskInfo array 包含任务的详细信息
 * $taskInfo[] = array('url'=>"/page3.php", "postdata"=>"act=test");
 * $taskInfo[] = array('url'=>"/page4.php", "postdata"=>"act=test", "prior"=>true);
 */
function addTaskQueue($taskInfo)
{
    for ($i = 2; $i >= 0; $i--) {
        $taskQueue = new SaeTaskQueue('order_exe_' . $i);
        if ($taskQueue->leftLength() >= count($taskInfo)) {
            break;
        }
    }
    $taskQueue->addTask($taskInfo);
    //将任务推入队列
    $ret = $taskQueue->push();
    //任务添加失败时输出错误码和错误信息
    if ($ret === false) {
        $taskQueue->errno();
        $taskQueue->errmsg();
    }
    /*错误码参考： - errno: 0 成功
    - errno: 1 认证失败
    - errno: 3 参数错误
    - errno: 10 队列不存在
    - errno: 11 队列已满或剩余长度不足
    - errno: 500 服务内部错误
    - errno: 999 未知错误
    - errno: 403 权限不足或超出配额*/
}

function dump($var)
{
    echo '<pre>';
    var_dump($var);
}

function formatTime($time)
{
    $now = time();
    $day = date('Y-m-d', $time);
    $today = date('Y-m-d');

    $dayArr = explode('-', $day);
    $todayArr = explode('-', $today);
    //距离的天数，这种方法超过30天则不一定准确，但是30天内是准确的，因为一个月可能是30天也可能是31天
    $days = ($todayArr[0] - $dayArr[0]) * 365 + (($todayArr[1] - $dayArr[1]) * 30) + ($todayArr[2] - $dayArr[2]);
    //距离的秒数
    $secs = $now - $time;
    if ($todayArr[0] - $dayArr[0] > 0 && $days > 3) {//跨年且超过3天
        return date('Y-m-d', $time);
    } else {
        if ($days < 1) {//今天
            if ($secs < 60) return $secs . '秒前';
            elseif ($secs < 3600) return floor($secs / 60) . "分钟前";
            else return floor($secs / 3600) . "小时前";
        } else if ($days < 2) {//昨天
            $hour = date('h', $time);
            return "昨天" . $hour . '点';
        } elseif ($days < 3) {//前天
            $hour = date('h', $time);
            return "前天" . $hour . '点';
        } else {//三天前
            return date('m月d号', $time);
        }
    }
}