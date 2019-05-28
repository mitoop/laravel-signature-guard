# Laravel Request Guard

系统间接口调用是开发中经常遇到的场景, 安全, 客户端发起请求方便, 服务端处理请求方便, 灵活的配置等都是设计时候考量的因素.

**安全** : 

系统间接口交互通常都是基于 HMAC (消息认证码)的方式来保证安全, HMAC 有哈希, 有密匙, 密匙保存在服务端, 所以很适合系统间接口的安全验证.

Laravel Request Guard 使用 `sha256` 哈希函数, sha256目前足够安全(相对于`sha1` `md5`) 快速(相对于`sha3`).

Laravel Request Guard 使用 `nonce` + `timestamp` 来防止重放攻击.

**请求** : 

Laravel Request Guard 依赖并使用 `guzzle/guzzle` 来完成请求发起, 参数设置和 `guzzle/guzzle` 保持一致, 
同时在使用上运用了 Laravel Manager 的语法糖, 构建请求方便灵活, 详情看下方 `请求` 部分.

**响应** :
 
每次请求总会返回 `\Mitoop\ApiSignature\SignatureResponse` 对象(即使服务端发生了异常), 该对象提供了简洁有力的方法来告别`try catch`. 详情看下方 `响应` 部分.

**配置** : 

配置支持多客户端, 并且支持同时充当客户端和服务端.

**须知** :

只支持 Laravel.
 
## 安装 Install

`composer require mitoop/laravel-signature-guard`

## 要求 Require

- Laravel 5.8 (当前线上运行在5.8, 其他版本未做测试)
- PHP 7.1.3+

## 配置 Config

1 . 安装过后运行 `php artisan vendor:publish --provider="Mitoop\Signature\ServiceProvider"`，会生成 `config/api-clients.php` 配置文件.

2 . 如果需要使用 Facade

```php
'aliases' => [
    // ...
    'Client' => Mitoop\Signature\Facades\Client::class,
]
```

3 . `api-clients.php` 配置说明

```php
    'default' => 'client-one', // 默认的客户端 作为发起方时, 默认连接的客户端

    'clients' => [ // 客户端组
        'client-one' => [ // 一个客户端
            'app_id'         => env('SIGN_CLIENT_ONE_APP_ID', 'app id'), // 客户端的 app id [必填]
            'app_secret'     => env('SIGN_CLIENT_ONE_APP_SECRET', 'app secret'),// 客户端的密匙 [必填]
            'scheme'         => env('SIGN_CLIENT_ONE_SCHEME', 'http'),// 客户端的 scheme 支持 http, https [必填]
            'host'           => env('SIGN_CLIENT_ONE_HOST', ''),// 客户端的 基础host 例如 : baidu.com 或者 192.168.11.11 [必填]
            'ip'             => env('SIGN_CLIENT_ONE_IP', ''),// 客户端的 host 对应的ip [选填]
            'port'           => env('SIGN_CLIENT_ONE_PORT', 80),// 客户端的 host 使用的端口 [必填]
            'https_cert_pem' => env('SIGN_CLIENT_ONE_HTTPS_CERT_PEM', false),// 发起 https 请求时候用到的证书 [选填]
                                                                             // 可选值 : 
                                                                             // false : 关闭证书验证
                                                                             // true : 开启 SSL 验证, 并使用系统的 CA 来处理
                                                                             // 自定义证书路径 : 开启 SSL 验证, 并使用自定义的 CA 来处理
            'enable_log'     => true, // 开启请求日志 true 或者 false 推荐 true
            
            // 可以把请求的地址数组放在这里 actions 不是固定的 可以自定义
            'actions' => [
               'foo' => '/foo/abr',
               'hello' => 'hello/world'
            ]
        ],

        'another-client' => [ // 另一个客户端
            'app_id'         => env('SIGN_ANOTHER_CLIENT_APP_ID', 'app id'),
            'app_secret'     => env('SIGN_ANOTHER_CLIENT_APP_SECRET', 'app secret'),
            'scheme'         => env('SIGN_ANOTHER_CLIENT_SCHEME', 'http'),
            'host'           => env('SIGN_ANOTHER_CLIENT_HOST', ''),
            'ip'             => env('SIGN_ANOTHER_CLIENT_IP', ''),
            'port'           => env('SIGN_ANOTHER_CLIENT_PORT', 80),
            'https_cert_pem' => env('SIGN_ANOTHER_CLIENT_HTTPS_CERT_PEM', false),
            'enable_log'     => true,
        ],
        //... more clients
    ],

    'identity' => 'mitoop-dev-server', // 当前系统身份标识 作为发起方时使用, 标识当前请求来源于该系统
 ``` 
 


## 请求 Request
请求支持 `get`, `post`, `put`, `delete` 四种方法. 下面以 `post` 方法为例. 假如设置了`alias` 为 `Client`

```php
// 向默认客户端发起请求 配置中 client-one 为默认客户端, 这里就是直接向 client-one 发起请求

Client::post('/api/demo', ['请求参数数组']);

```

向其他客户端发起请求 这个时候需要指定 `connect` 的客户端

```php

Client::connect('any-client')->post('/api/demo', ['请求参数数组']);

```

```php
// 容器模式

$client = app(\Mitoop\Signature\Client::class);

$client->connect('any-client')->post('/api/demo', ['请求参数数组']);

```

```php
// 请求参数说明 请求参数遵从于 `guzzle`

[
     'query' => [
        'foo' => 'bar'
     ],
     'form_params' => [
             'foo' => 'bar'
     ],
     'json' => [
             'foo' => 'bar'
     ],
]

// query 是典型的 `get` 请求传参方式, 等同于 url 后的问号参数
// form_params 是典型的 `post` 请求传参方式, 这应该是最常见的 POST 提交数据的方式, 最终就会以 application/x-www-form-urlencoded 方式提交数据
// json JSON 格式支持比键值对复杂得多的结构化数据，这一点很有用, 最终就会以 application/json 方式提交数据
// !!! 请不要 form_params 和 json 同时使用, query 可以和两者之一同时使用
```

## 响应 Response

每次请求总会返回 `\Mitoop\Signature\SignatureResponse` 对象(即使服务端发生了异常), 
该对象提供了简洁有力的方法来告别`try catch`.

```php
$signatureResponse->isSuccess(); // 请求是否成功 
$signatureResponse->isOk(); //  isSuccess别名方法
$signatureResponse->body(); // 获取原始输出信息
$signatureResponse->json(); // 获取json格式的数据 

// 典型用法如 :
 
if($signatureResponse->isOk()) {
   if($json = $signatureResponse->json()) {
      // 处理自己的业务代码
   }
}

// 告别处理响应时 try catch 冗长的处理

```

更多使用方法参考 [这里](https://github.com/mitoop/laravel-signature-guard/blob/master/src/SignatureResponse.php)


### 事件 Events

requesting 和 requested 是请求前和请求后的事件，可以方便地对请求进行额外的处理.

```php
Client::requesting(function (\Mitoop\Signature\Client $client) {
    // 请求发起之前
});

Client::requested(function (\Mitoop\Signature\Client $client) {
    // 请求发起之后
});
```

### 作为服务端
Laravel Request Guard 使用 Laravel 本身提供的 `guard` 机制来完成身份验证.

首先需要配置一个 guard : 
```php
'guards' => [
     //... 
     'server-api' => [ // 这里的 `server-api` 是 `guard` 名称, 可以自定义
         'driver' => 'signature', // 指定驱动为 `signature`
     ],
],
```

其次定义路由中间件, Laravel Request Guard 本身提供了一个中间件 `\Mitoop\Signature\Middleware\RequestGuardAuth` 
假如你使用该中间件 那么在 `\App\Http\Kernel` 的 `$routeMiddleware` 上配置 :
```php
  //...
  'auth.signature' => \Mitoop\Signature\Middleware\RequestGuardAuth::class,
```

`\Mitoop\Signature\Middleware\RequestGuardAuth` 中间件非常简单, 你也可以自己定义一个中间件.

```php
public function handle($request, Closure $next, $guard)
{
     Auth::guard($guard)->check(); // 对请求进行验证, 如果验证错误抛出 `\Mitoop\Signature\Exception\InvalidSignatureException` 异常

     return $next($request);
}
```

最后在路由里使用 `auth.signature:server-api` 中间件就可以了
```php

// 现在 `/test` 请求必需通过验证才能访问的到

Route::post('/test', function () {
   dd(request()->all());   
})->middleware('auth.signature:server-api');

// 本机测试

Route::get('/tt', function () {
    // 向 `tuning` 客户端发起请求 `tuning` 客户端可以配置为当前项目的 host
    $response = Client::connect('tuning')->post('test', [
        'form_params' => [
            'foo' => 'bar',
        ],
        'query' => [
            'hello' => 'world',
        ],
     ]);
     
    return $response->body();
});

// tuning 配置参考
   'tuning' => [
            'app_id'         => '100001',
            'app_secret'     => 'tuning',
            'scheme'         => 'http',
            'host'           => 'foobar.test', // 本地项目域名
            'ip'             => '127.0.0.1',
            'port'           => 80,
            'https_cert_pem' => false,
            'enable_log'     => true,
  ],
```

其他可用的 Auth 方法

```php
Auth::guard('server-api')->check();
Auth::guard('server-api')->id();
Auth::guard('server-api')->user();

// 检查是否通过验证 
Auth::guard('server-api')->check();

// 返回发起方的 config 里的 app_id
$appId = Auth::guard('server-api')->id();
 
// 返回 `\Mitoop\Signature\ClientUser` 实例. 里面包含了发起方的 config 信息(不包含密匙)
$user = Auth::guard('server-api')->user(); 

// 还可以从 ClientUser 实例上获取每一项配置
$appId = $user->app_id;
$host = $user->host;
//...
```

## Contributor

[zhuzhichao](https://github.com/zhuzhichao)