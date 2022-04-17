---
title: 透過 SplatNet APIs 獲取遊戲紀錄
tags: [SplatNet, Nintendo Switch, Splatoon 2]
date: 2022-04-10 10:07:22
category: Note
---

## SplatNet Token

要透過 SplatNet APIs 獲取遊戲紀錄的話，首先必須要登入帳號，並取得 cookies。一個比較簡單快速的方式是透過 [splatnet2statink](https://github.com/frozenpandaman/splatnet2statink) 這個 repo 來取得。

### splatnet2statink

Clone 下來後執行：

```bash
python3 splatnet2statink.py -M 100
```

上述的指令是每 150 秒獲取一次資料，在第一次執行的時候會首先要你登入，並產生 cookies。我們可以利用此 repo 產生的 cookies 來認證。

- 登入完後開啟 *config.txt*，並存下 cookies

```json
{
    "api_key": "your_key",
    "cookie": "your_cookie",
    "session_token": "your_token",
    "user_lang": "en-US"
}
```

## APIs

下述為 SplatNet APIs 的清單：
- [SplatNet APIs](https://github.com/msruback/MoNet2/wiki/Splatnet-2-API)
- 常使用到的如下
    - `/api/results` 獲取近 50 場紀錄
    - `/api/records` 獲取用戶統計數據
    - `/api/nickname_and_icon?id=your_id` 獲取用戶名及頭像
    - `/api/records/hero` 獲取英雄模式紀錄

### Request

要使用 APIs 需要在 header 帶入 cookies，格式為 `iksm_session=api_cookie`

- 完整 header 參考下表（以 PHP cURL lib 展示）

```php=
curl_setopt_array($ch, array (
    CURLOPT_HEADER          =>  0,
    CURLOPT_RETURNTRANSFER  =>  true,
    CURLOPT_COOKIE          => 'iksm_session=' . $api_cookie,
    CURLOPT_HTTPHEADER      => array (
        'Host'              => 'app.splatoon2.nintendo.net',
        'x-unique-id'       => $app_unique_id,
        'x-requested-with'  => 'XMLHttpRequest',
        'x-timezone-offset' => strval($app_timezone_offset),
        'User-Agent'        => $app_user_agent,
        'Accept'            => '*/*',
        'Referer'           => 'https://app.splatoon2.nintendo.net/home',
        'Accept-Encoding'   => 'gzip, deflate',
        'Accept-Language'   => $user_lang,
    )
));
```

接下來只需連線至 API 網址即可，完整範例可參考 [GitHub](https://github.com/luswdev/SplatoonBot/blob/master/src/splatnet.php)
