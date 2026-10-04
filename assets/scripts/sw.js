/*
  プシューメモのService Worker

  役割は「静的なファイルを速く出す」ことだけです。
  エディタの操作（保存・読込・置換・アカウント同期）はすべてPHPへのPOSTで行うため、
  ここで横取りしてはいけません。以前の版は全リクエストを respondWith していたので、
  POSTやリダイレクトが絡む処理、そしてダウンロードのレスポンスが壊れる可能性がありました。
*/

var CACHE_NAME = "p-memo-v2";

var PRECACHE_PATHS = [
  "./assets/styles/memo.css",
  "./assets/scripts/memo.js"
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      // 1つでも取得に失敗すると addAll は全体が失敗するので、個別に入れます。
      // 一部が取れなくてもインストール自体は成功させたいためです。
      return Promise.all(PRECACHE_PATHS.map(function (path) {
        return cache.add(path)["catch"](function () { /* 取れなければキャッシュしないだけです */ });
      }));
    })
  );
  self.skipWaiting();
});

self.addEventListener("activate", function (event) {
  // 古い版のキャッシュ（saveVar1）を片付けます
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        return key === CACHE_NAME ? null : caches.delete(key);
      }));
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener("fetch", function (event) {
  var request = event.request;

  // GET以外（保存・読込・ログインなどのPOST）には一切触れません。
  // ここを通すと、ダウンロードのレスポンスやリダイレクトが壊れます。
  if (request.method !== "GET") {
    return;
  }

  // HTMLは常にサーバから取ります。
  // ログイン状態やアカウントに保存したメモの一覧はPHPが埋め込んで返すため、
  // キャッシュを返すと古いログイン状態が表示されてしまいます。
  if (request.mode === "navigate") {
    return;
  }

  // 静的ファイルだけ、キャッシュがあればそれを返します
  event.respondWith(
    caches.match(request).then(function (cached) {
      return cached || fetch(request);
    })
  );
});
