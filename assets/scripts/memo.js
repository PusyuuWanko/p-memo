/*****************************************
  *----------------------------------
  |  ThisJsVersion: 3.0.0         |
  |  © 2021-2026 By ISAMI ABE     |
  |  LastUpdate: 2026-07-29       |
  |  License: MIT License         |
  |  PusyuuMemoNote               |
----------------------------------*
******************************************/

/*
  このファイルの役割が2.x系から変わりました。

  以前はエディタのHTMLそのものをJavaScriptが innerHTML で組み立てていたため、
  JavaScriptが動かないブラウザではメモ帳が一切表示されませんでした。
  現在はエディタをPHP（index.php）が出力しており、入力・保存・読込・置換・
  大文字小文字の変換はすべて普通のフォーム送信としてサーバ側で完結します。

  このファイルは、その上に「あると快適な機能」だけを足します。
    - 文字数のリアルタイム表示
    - 書き込みのたびの一時保存（localStorage）
    - 置換フォームのモーダル化
    - 表示の拡大縮小、すべてコピー
    - 最新のブラウザでの「名前を付けて保存」ダイアログ

  そのため、ここが丸ごと読み込みに失敗しても、メモ帳は動きます。
  古い実行環境でも構文エラーで止まらないよう、アロー関数やテンプレートリテラル、
  const/let といった新しい書き方は使わず、var と function だけで書いています。
*/

(function () {
  "use strict";

  // -------------------------------------------------------------------
  // 小さなヘルパー（古いブラウザにも同じ書き方で通るようにするため）
  // -------------------------------------------------------------------

  function byId(id) {
    return document.getElementById(id);
  }

  function on(element, eventName, handler) {
    if (!element) { return; }
    if (element.addEventListener) {
      element.addEventListener(eventName, handler, false);
    } else if (element.attachEvent) {
      // IE8以下
      element.attachEvent("on" + eventName, handler);
    }
  }

  function hasClass(element, name) {
    return (" " + element.className + " ").indexOf(" " + name + " ") > -1;
  }

  // -------------------------------------------------------------------
  // 参照する要素
  // -------------------------------------------------------------------

  var form, textarea, counter, charsetSelect, eolSelect, fileNameInput, replaceModal;

  function collectElements() {
    form          = byId("memoForm");
    textarea      = byId("memoText");
    counter       = byId("moziCount");
    charsetSelect = byId("charsetSelect");
    eolSelect     = byId("eolSelect");
    fileNameInput = byId("fileName");
    replaceModal  = byId("replaceModal");
    return !!textarea;
  }

  // -------------------------------------------------------------------
  // 文字数カウントと一時保存
  // -------------------------------------------------------------------

  function updateMoziCount() {
    if (!counter || !textarea) { return; }
    counter.innerHTML = textarea.value.length + "Mozi";
  }

  function saveTemporarily() {
    if (!textarea) { return; }
    try {
      window.localStorage.setItem("Memo", textarea.value);
    } catch (e) {
      // プライベートブラウジングや容量超過で失敗することがあります。
      // 一時保存はあくまで保険なので、失敗してもメモ帳自体は使えるよう黙って続けます。
    }
  }

  function restoreTemporarily() {
    if (!textarea) { return; }
    // サーバがすでに内容を描き出している場合（クラウドメモを開いた直後や、
    // 置換などの操作から戻ってきた直後）は、そちらが最新なので上書きしません。
    if (textarea.value !== "") { return; }
    try {
      var stored = window.localStorage.getItem("Memo");
      if (stored) { textarea.value = stored; }
    } catch (e) {
      // localStorage が使えない環境では復元しないだけです
    }
  }

  // -------------------------------------------------------------------
  // 公開する関数（HTML側の onclick から呼ばれます）
  // -------------------------------------------------------------------

  window.updateMoziCount = updateMoziCount;
  window.saveTemporarily = saveTemporarily;

  window.zoomInCanvas = function () {
    if (!textarea) { return; }
    var size = parseFloat(window.getComputedStyle ? window.getComputedStyle(textarea).fontSize : "16");
    if (isNaN(size)) { size = 16; }
    textarea.style.fontSize = (size + 2) + "px";
  };

  window.zoomOutCanvas = function () {
    if (!textarea) { return; }
    var size = parseFloat(window.getComputedStyle ? window.getComputedStyle(textarea).fontSize : "16");
    if (isNaN(size)) { size = 16; }
    textarea.style.fontSize = Math.max(8, size - 2) + "px";
  };

  window.allcopy = function () {
    if (!textarea) { return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(textarea.value);
      alert("すべてコピーしました");
      return;
    }
    // クリップボードAPIが無い環境では、選択してコピーする昔ながらの方法にします
    textarea.focus();
    textarea.select();
    var copied = false;
    try {
      copied = document.execCommand("copy");
    } catch (e) {
      copied = false;
    }
    alert(copied ? "すべてコピーしました" : "このブラウザでは自動コピーができませんでした。選択されている状態なので、キーボードのCtrl+C（macOSはCommand+C）でコピーしてください。");
  };

  window.showReplaceSettingModal = function () {
    if (replaceModal) { replaceModal.style.display = "block"; }
  };

  window.closeReplaceSettingModal = function () {
    if (replaceModal) { replaceModal.style.display = "none"; }
  };

  // -------------------------------------------------------------------
  // 保存（ダウンロード）の上乗せ
  //
  // フォームはそのまま送信すればサーバ側で確実に保存できます。ここでは、
  // 対応しているブラウザに限って「名前を付けて保存」ダイアログを出すだけの
  // 上乗せを行います。文字コードがUTF-8以外のときは、JavaScriptでは
  // Shift_JISなどへ変換できないため、必ずサーバ側に任せます。
  // -------------------------------------------------------------------

  function currentCharset() {
    return charsetSelect ? charsetSelect.value : "UTF-8";
  }

  function currentEolString() {
    var value = eolSelect ? eolSelect.value : "LF";
    if (value === "CRLF") { return "\r\n"; }
    if (value === "CR") { return "\r"; }
    return "\n";
  }

  function buildFileName() {
    var name = fileNameInput ? fileNameInput.value : "";
    name = name.replace(/^\s+|\s+$/g, "");
    if (name === "") {
      var now = new Date();
      function pad(n) { return n < 10 ? "0" + n : "" + n; }
      return "P-memo_" + now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) +
             "_" + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds()) + ".txt";
    }
    return name;
  }

  /**
   * ファイル名に拡張子が無いときの確認。
   * サーバ側は確認できないので黙って .txt を付けますが、
   * JavaScriptが動く環境では従来どおり選ばせます。
   * 戻り値: 送信を続行してよければ true
   */
  function confirmExtension() {
    if (!fileNameInput) { return true; }
    var name = fileNameInput.value.replace(/^\s+|\s+$/g, "");
    if (name === "") { return true; }

    var parts = name.split(".");
    if (parts.length > 1 && parts[parts.length - 1].length >= 1) { return true; }

    if (confirm("問題が発生しました、拡張子がついていません。\n\n.txtとして保存する場合はOKを押してください。")) {
      fileNameInput.value = name + ".txt";
      return true;
    }
    if (confirm("拡張子なしで保存しますか？\n\nOKを押さない場合は保存処理はキャンセルされます。")) {
      return true;
    }
    alert("保存処理をキャンセルしました。");
    return false;
  }

  /**
   * showSaveFilePicker が使える、かつUTF-8で良い場合だけ、ブラウザ側で保存します。
   * 戻り値: ブラウザ側で処理したなら true（＝フォーム送信を止めてよい）
   */
  function trySaveWithPicker() {
    var charset = currentCharset();
    if (charset !== "UTF-8" && charset !== "UTF-8-BOM") { return false; }
    if (!window.showSaveFilePicker) { return false; }
    if (!textarea) { return false; }

    var text = textarea.value.replace(/\r\n|\r/g, "\n");
    var eol = currentEolString();
    if (eol !== "\n") { text = text.replace(/\n/g, eol); }
    if (charset === "UTF-8-BOM") { text = "﻿" + text; }

    window.showSaveFilePicker({
      suggestedName: buildFileName(),
      types: [{ description: "Text Files", accept: { "text/plain": [".txt"] } }]
    }).then(function (handle) {
      return handle.createWritable().then(function (writable) {
        return writable.write(text).then(function () {
          return writable.close();
        });
      });
    }).then(function () {
      alert("正常にメモのセーブができました");
    })["catch"](function (error) {
      var message = (error && error.message) ? error.message : "";
      if (error && error.name === "AbortError") {
        alert("保存処理がユーザーによって中断されました。");
      } else if (message.indexOf("permission denied") > -1 || (error && error.name === "NotAllowedError")) {
        alert("ファイル保存の権限がありません。");
      } else {
        alert("保存中に問題が発生しました。もう一度お試しいただくか、解決できない場合は問題の詳細とともに管理者へお伝えください。問題の詳細は：" + message + "です。");
      }
    });

    return true;
  }

  // -------------------------------------------------------------------
  // 読込（ファイル選択）の上乗せ
  //
  // action=upload はサーバ側でファイルを受け取って文字コード判別まで行うため
  // 古いShift_JISのtxtも正しく開けますが、リクエスト全体がpost_max_sizeを
  // 超えると$_POSTごと空になり、エラーも出ないまま何も起きません（古い端末で
  // 大きいファイルを開こうとすると起きやすい問題です）。
  // 「読込」ボタンを押した瞬間（フォームのsubmit）に、FileReaderが使える環境
  // だけブラウザ側だけで読み込んでエディタへ反映し、そのままサーバへは送らない
  // ようにします。ファイルを選んだだけの時点では何もしません（今のHTMLには
  // 「読込」ボタンが独立して存在するため、そちらを押すタイミングに合わせています。
  // 以前のバージョン（2.x系）はファイル選択ダイアログを閉じた時点＝選択の完了が
  // そのまま読込の実行を意味していたため、選択即読込で構いませんでした）。
  // 文字コード自動判別はできなくなる（UTF-8として読みます）ため、Shift_JIS等の
  // 古いtxtで文字化けする場合は、FileReaderが無い環境（別のブラウザや古い端末）で
  // 読み込み直せば、これまで通りサーバ側の文字コード判別が使えます。
  // -------------------------------------------------------------------

  var uploadForm, fileInput;

  function setupClientSideUpload() {
    uploadForm = byId("memoFile") ? byId("memoFile").form : null;
    fileInput  = byId("memoFile");
    if (!uploadForm || !fileInput || !window.FileReader || !textarea) { return; }

    on(uploadForm, "submit", function (event) {
      var file = fileInput.files && fileInput.files[0];
      if (!file) {
        // ファイルが選ばれていなければ、これまで通りサーバに送って
        // 「ファイルが選ばれていません」のエラー表示を出させます。
        return true;
      }

      var reader = new FileReader();
      reader.onload = function (loadEvent) {
        textarea.value = loadEvent.target.result;
        updateMoziCount();
        saveTemporarily();
        if (fileNameInput) { fileNameInput.value = file.name; }
      };
      reader.onerror = function () {
        alert("ファイルの読み込みに失敗しました。");
      };
      reader.readAsText(file);

      return stopSubmit(event);
    });
  }

  // -------------------------------------------------------------------
  // フォーム送信の横取り
  //
  // どのボタンが押されたのかを覚えておき、そのボタンに応じた確認を挟みます。
  // 何もしなければ、そのまま普通のフォーム送信としてサーバへ飛びます。
  // -------------------------------------------------------------------

  var pressedAction = "";

  function rememberPressedButton(event) {
    // 前回の記憶を引きずらないよう、クリックのたびに一度空にします
    pressedAction = "";
    var target = event.target || event.srcElement;
    while (target && target !== form) {
      if (target.tagName && target.tagName.toLowerCase() === "button" && target.name === "action") {
        pressedAction = target.value;
        return;
      }
      target = target.parentNode;
    }
  }

  function stopSubmit(event) {
    if (event.preventDefault) { event.preventDefault(); } else { event.returnValue = false; }
    return false;
  }

  function handleSubmit(event) {
    if (pressedAction === "clear") {
      if (!confirm("キャンバス上のメモデータを削除しますか？")) {
        alert("キャンバス上のメモデータの削除処理をキャンセルしました。");
        return stopSubmit(event);
      }
      // 一時保存の方も消すかどうかを続けて確認します
      if (confirm("一時保存のデータも含めて完全に削除しますか？\n\n※OKを押すと、リロードによる復元が行えなくなります。")) {
        try { window.localStorage.removeItem("Memo"); } catch (e) {}
      }
      return true;
    }

    if (pressedAction === "replace") {
      var search = byId("replaceAllowWord");
      if (search && search.value === "") {
        alert("置換対象の文字が入力されていません、置換対象文字は必須です");
        return stopSubmit(event);
      }
      return true;
    }

    if (pressedAction === "zoom_in" || pressedAction === "zoom_out") {
      // JavaScriptが動いていれば、ページの再読み込みを挟まずその場で
      // 文字サイズを変えられます（action=zoom_in/zoom_outのPHP側処理は、
      // JavaScriptが動かない環境向けのフォールバックです）。
      if (pressedAction === "zoom_in") {
        window.zoomInCanvas();
      } else {
        window.zoomOutCanvas();
      }
      return stopSubmit(event);
    }

    if (pressedAction === "download" || pressedAction === "") {
      if (!confirmExtension()) { return stopSubmit(event); }
      if (trySaveWithPicker()) { return stopSubmit(event); }
      return true;
    }

    return true;
  }

  // -------------------------------------------------------------------
  // 起動
  // -------------------------------------------------------------------

  function init() {
    // JavaScriptが動いていることをCSSへ伝えます。
    // これで .js_only のボタンが現れ、置換フォームがモーダルになります。
    var root = document.documentElement;
    if (hasClass(root, "no-js")) {
      root.className = root.className.replace(/(^|\s)no-js(\s|$)/, "$1js$2");
    } else if (!hasClass(root, "js")) {
      root.className += " js";
    }

    if (!collectElements()) { return; }

    restoreTemporarily();
    updateMoziCount();

    on(textarea, "input", function () {
      updateMoziCount();
      saveTemporarily();
    });
    // input イベントが無いIE8以下向け
    on(textarea, "keyup", function () {
      updateMoziCount();
      saveTemporarily();
    });

    if (form) {
      on(form, "click", rememberPressedButton);
      on(form, "submit", handleSubmit);
    }

    setupClientSideUpload();

    // プロダクト一覧を開いている間は背面をスクロールさせません
    on(window, "popstate", function () {
      document.body.style.overflow = (window.location.hash === "#products" || window.location.hash === "#login") ? "hidden" : "";
    });

    // Service Worker はあくまで表示の高速化のためのものなので、
    // 登録に失敗してもメモ帳の動作には影響しません。
    if (navigator.serviceWorker && navigator.serviceWorker.register) {
      navigator.serviceWorker.register("./assets/scripts/sw.js")["catch"](function (err) {
        if (window.console && console.error) {
          console.error("Service Worker registration failed:", err);
        }
      });
    }
  }

  if (document.readyState === "complete" || document.readyState === "interactive") {
    init();
  } else {
    on(document, "DOMContentLoaded", init);
    // DOMContentLoaded が無いIE8以下向け
    on(window, "load", function () {
      if (!textarea) { init(); }
    });
  }
})();
