MyFleetGirls-proxy
--

MyFleetGirls自带的client似乎有点问题，有时候会猫。这东西可以不用MyFleetGirls自带的client向MyFleetGirls直接提交游戏数据。不过作者不太支持这么做（[#226](https://github.com/ponkotuy/MyFleetGirls/issues/226)）。

### 如何使用

#### 开始之前
光有这个还不行，你需要用个别的插件把游戏通信数据提交给这个东西处理。这个工具需要把数据按以下格式以`POST`方式给`inxex.php`
|Key|Value|
|---|---|
|`path`|游戏请求的路径，如`/kcsapi/api_port/port`|
|`svdata`|请求返回值，包括开头的`svdata=`|
|`gamepost`|游戏请求时的 query string 值，记得保护好`api_token`这一项|
|u|可选，验证身份什么的|

#### 准备
在`mfg-auth.sample.php`里填上mfg的账号信息和游戏的账号信息，另存为`mfg-auth.php`。可能还需要有写入文件的权限。然后开启服务器，剩下的就是A过去了。

### 已知的问题
* 图鉴暂时还不会提交给mfg服务器。
* 切换队伍后直接点左下角吊车进改修工厂改修可能会提交不正确的第二位信息。
* 可能还有其他问题，如果对你造成困扰，那真是……太棒啦
