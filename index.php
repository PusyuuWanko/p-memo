<?php

// 他のプロダクト(main 等)の住所の頭。台帳(pusyuu_registry)から決まります。
// 【ドメインを書き戻さないこと】以前はテンプレートに https://pusyuuwanko.com 等を固定で
// 書いていたため、IPやLAN、別のドメインから入った人の画面でも公開ドメインへ取りに
// 行っていました。$public は OGP のように、よそ(SNS)が取りに来るものだけ true。
// 台帳の係が無い・読めないときは空文字(=このページのホストの / から)に倒れます。
function pmemoSiteBase(string $id, bool $public = false): string {
  return class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::baseHtml($id, $public) : '';
}
// =====================================================================
// セッションの窓口(p-memoの「顔」)
//
// 共有スクリプト pusyuu_session.php があれば、そちらへ委ねます。**クッキーを切って
// いる利用者にも、URLの引き換えコードでセッションが続きます**(ログインも保てます)。
// 機械(クローラ・AIの取得)の判定も、このファイルの中にあります(PusyuuSessionRobot)。
//
// 【$_SESSION はこれまでどおり使えます】共有スクリプト側が $_SESSION と自分の保存先を
// 同期します(PusyuuSession::startWithSuperglobal())。だから p-memo 本文の
// $_SESSION(38か所)は1か所も書き換えていません。
//
// 【PHPのセッション関数だけは置き換えが必要です】session_regenerate_id() /
// session_write_close() は「使われていないPHPのセッション」を相手にしてしまい、
// **黙って効かなくなります。** 下の PmemoSession 経由で呼んでください。
//
// 【共有スクリプトが無い環境でも動くこと】以前とまったく同じ始め方をする枝を残して
// あります。どちらの枝も同じ意味になるように揃えること。
// =====================================================================
$directry = "./../main/pusyuusystem/scripts/php_scripts/pusyuu_session.php";
if (file_exists($directry)) {
  require_once($directry);
}

class PmemoSession {
  /** 共有スクリプトを通しているか。start() で1回だけ決まります。 */
  private static $viaLibrary = false;

  /** セッションを始めます。**出力より前に1回だけ**呼んでください。 */
  public static function start() {
    if (class_exists('PusyuuSession')) {
      self::$viaLibrary = true;
      PusyuuSession::startWithSuperglobal();
    } else {
      self::$viaLibrary = false;
      // 共有スクリプトが無い環境。以前の p-memo とまったく同じ始め方です。
      //
      // 【SameSiteは必ずLax】Strictにすると、メイキィのログイン画面から戻ってきた
      // トップレベル遷移にCookieが送られず、毎回「別のセッション」として扱われます。
      //
      // 【secureは実際にHTTPSで来ているかで決める】無条件にtrueにすると、HTTPで
      // 配信されている環境ではブラウザがセッションCookieを一切保存できません。
      $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
      session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
      session_cache_limiter("nocache");
      session_start();
    }
  }

  /** セッションIDを作り直します(ログイン=権限昇格のときのセッション固定化対策)。 */
  public static function regenerateId() {
    if (self::$viaLibrary) {
      PusyuuSession::regenerateId(true);
    } else {
      session_regenerate_id(true);
    }
  }

  /**
   * セッションを閉じます(以降は書けません)。添付の配信のように、長い出力の前に
   * ロックを手放したい場面で使います。
   * 【閉じる前に書き終えていること】閉じた後の変更は保存されません。
   */
  public static function close() {
    if (self::$viaLibrary) {
      PusyuuSession::close();
    } else {
      session_write_close();
    }
  }
}

PmemoSession::start();
// 古いInternet Explorerは、クロスドメインのSSOリダイレクト後に戻ってきた
// 側でCookie(セッション)が保持されない場合がある。IEはP3Pポリシーの宣言が
// 無いCookieを"サードパーティ的"とみなして拒否することがあるための既知の
// 挙動で、この宣言はその互換性のためだけのものです(実際のプライバシー
// ポリシーの内容を表すものではありません)。accounts側のログイン画面にも
// 同じ宣言を入れています。
header('P3P: CP="CAO PSA OUR"');

$dir = "./";
$directry = $dir . "../main/pusyuusystem/scripts/php_scripts/panic_handler.php";
if (file_exists($directry)) {
  require_once($directry);
}

$directry = $dir . "../main/pusyuusystem/scripts/php_scripts/usage_tracker.php";
if (file_exists($directry)) {
  require_once($directry);
}
if (function_exists('pusyuuRecordProductUsage')) {
  pusyuuRecordProductUsage('p-memo');
}

$directry = $dir . "../main/pusyuusystem/scripts/php_scripts/notation.php";
if (file_exists($directry)) {
  require_once($directry);
}
// 機械(クローラ・AIの取得)の判定は、上の pusyuu_session.php の中にあります
// (PusyuuSessionRobot。以前は bot_detect.php という別ファイルでした)。
// PusyuuSession::looksLikeRobot() か、従来どおり pusyuuIsKnownCrawler() で使えます。

// =====================================================================
// アカウント機能の実体は p-memo の中にはありません。共有スクリプトが提供する
// pusyuuAccount〜() へ頼み、p-memo はその答えを自分の言葉へ翻訳するだけです。
//
// 以前はここに、メイキィと話すクラスの全文(500行あまり)が埋め込まれていました。
// 同じものが6プロダクトに複製され「直したら他へ貼り替える」運用で揃えることに
// なっていましたが、実際には揃わず、toolboxの複製だけが1文字違っていたせいで
// toolboxのアカウント機能が全滅していました(2026-09-06)。持ち物が1つなら、
// そもそも食い違いようがありません。
//
// 【使うときは必ず PmemoAccount を通すこと】
// 本文から直接 pusyuuAccount〜() を呼ばないでください。共有スクリプトが無い環境では
// それらの関数自体が存在せず、「Call to undefined function」で致命的エラーになり、
// p-memoのページが1行も表示されなくなります。
//
// 読み込み方は上の panic_handler / usage_tracker / notation / bot_detect と同じです。
// 上へ探しには行きません。単独で動かすというのは「階層をずらす」ことではなく
// 「別の環境へ丸ごと持って行く」ことなので、その先には main/ ごと無いのが普通で、
// そのときはアカウント機能だけが畳まれた状態で普通に動きます。
// =====================================================================
$directry = $dir . "../main/pusyuusystem/scripts/php_scripts/meikiee_client.php";
if (file_exists($directry)) {
  require_once($directry);
}

// ================================================================
// p-memo から見たアカウント機能(p-memoの「顔」)
//
// ここが持つのは「p-memoにとっての意味」だけです——自分のセッションのどのキーに
// トークンを入れるか、メモの保存先をどう指すか、使えないときに何を返すか。
// ================================================================
class PmemoAccount {
  /**
   * アカウント機能が今使えるかどうか。他のメソッドは全部これを先に見ます。
   * 画面に「ログイン」ボタンを出すかどうかの判断にも使ってください。届かないときに
   * ボタンを出すと、押した利用者は行き止まりに突き当たります。
   */
  public static function ready(): bool {
    if (function_exists('pusyuuAccountReady')) {
      $result = pusyuuAccountReady();
    } else {
      // 共通のアカウント連携ファイルが置かれていないサーバ。単体で動かすための道です。
      $result = false;
    }

    return $result;
  }

  /** 使えないときの理由(利用者にそのまま見せてよい1文)。使える場合は空文字。 */
  public static function unavailableReason(): string {
    if (function_exists('pusyuuAccountUnavailableReason')) {
      $result = pusyuuAccountUnavailableReason();
    } else {
      $result = 'このサーバにはアカウント連携機能が設置されていないため、メモの保存と同期はご利用いただけません。';
    }

    return $result;
  }

  /**
   * 直近のログインが失敗していれば、その理由を1度だけ返します(読んだら消えます)。
   * 拾わないと、ログインの失敗は誰にも見えません(判定はリダイレクトの直前で終わり、
   * そのリクエストはそのまま消えるため)。
   */
  public static function takeSignInError(): string {
    if (function_exists('pusyuuAccountTakeSignInError')) {
      $result = pusyuuAccountTakeSignInError();
    } else {
      $result = '';
    }

    return $result;
  }

  /**
   * 「使えるなら $work を実行し、使えないなら $fallback を返す」。
   * 判定をここ1箇所に集めておけば、メソッドを足すときに書き忘れようがありません。
   * 書き忘れた1つが、共有スクリプトの無い環境でページを真っ白にします。
   * $fallback は引数なので先に評価されます。軽いものだけを渡してください。
   */
  private static function whenReady(callable $work, $fallback) {
    if (self::ready()) {
      $result = $work();
    } else {
      $result = $fallback;
    }

    return $result;
  }

  /**
   * p-memo自身のセッションが持っているトークン。未ログインなら空文字。
   * キー名がプロダクトごとに違う(pipsとtoolboxは 'accounts_token')ので、
   * 共有スクリプト側では扱えません。ここがp-memo固有の事情そのものです。
   */
  public static function token(): string {
    return $_SESSION['pusyuu_accounts_token'] ?? '';
  }

  /** ログイン画面へのリンク。使えないときは空文字(リンクを出さないでください)。 */
  public static function loginUrl(): string {
    return self::whenReady(static function () {
      return pusyuuAccountSignInUrl();
    }, '');
  }

  /** アカウント作成画面へのリンク。 */
  public static function createUrl(): string {
    return self::whenReady(static function () {
      return pusyuuAccountCreateUrl();
    }, '');
  }

  /** ログアウトの戻り先URL。使えないときは自分のトップへ戻します。 */
  public static function logoutUrl(): string {
    return self::whenReady(static function () {
      return pusyuuAccountSignOutUrl();
    }, './');
  }

  /** 手元のトークンを失効させます(画面遷移は伴いません)。 */
  public static function revokeToken(string $token): void {
    if (self::ready()) {
      pusyuuAccountRevokeToken($token);
    }
  }

  /**
   * トークンの持ち主(userid/username/name)。未ログインや使えないときはnull。
   *
   * 戻り値が null でも、それが「トークンが無効だった」のか「基盤に届かなかった」のかは
   * 区別が要ります。前者だけがログアウトさせてよい場合です。判定には rejected を見る
   * self::sessionResult() を使ってください。
   */
  public static function currentUser(): ?array {
    $res = self::sessionResult();

    if (!empty($res['ok'])) {
      $user = $res['user'];
    } else {
      $user = null;
    }

    return $user;
  }

  /**
   * currentUser() の生の結果。['ok'=>bool, 'user'=>array, 'rejected'=>bool, 'message'=>string]。
   *
   * 【rejected を必ず見てください】「ok以外なら全部ログアウト」と書くと、基盤が一時的に
   * 落ちただけで利用者のセッションが破棄され、ログインの控えまで消えてログインし直す
   * こともできなくなります。実際にpipsがその状態でした。
   */
  public static function sessionResult(): array {
    $token = self::token();

    if ($token === '') {
      // 未ログイン。rejected を false にしておくのが要点です。true にすると
      // 呼び出し側が「無効なトークンだった」と解釈してセッションを破棄します。
      $result = ['ok' => false, 'rejected' => false, 'message' => ''];
    } else {
      $result = self::whenReady(static function () use ($token) {
        return pusyuuAccountSelf($token);
      }, ['ok' => false, 'rejected' => false, 'message' => self::unavailableReason()]);
    }

    return $result;
  }

  // ----------------------------------------------------------------
  // メモの保存先
  //
  // メモ本体は共有ストレージ(実体はメイキィの中)に置きます。保存先の指定に使う
  // $userid は、ログイン時に受け取ったハッシュです。生のidではありません。
  //
  // 【メモは手元保存の受け皿へ落としません】メモは1件1MiBまで許す本文で、
  // ブラウザのセッションに置ける量ではありません。中途半端に手元へ貯めると、
  // 保存できたつもりで消える方が、保存できないと分かるより有害です。
  // 使えないときは、はっきり失敗を返して理由を見せます。
  // ----------------------------------------------------------------

  public static function storageList(string $userid): array {
    return self::whenReady(static function () use ($userid) {
      return pusyuuAccountStorageList($userid, 'p-memo');
    }, ['ok' => false, 'message' => self::unavailableReason()]);
  }

  public static function storageGet(string $userid, string $id): array {
    return self::whenReady(static function () use ($userid, $id) {
      return pusyuuAccountStorageGet($userid, 'p-memo', $id);
    }, ['ok' => false, 'message' => self::unavailableReason()]);
  }

  public static function storagePut(string $userid, string $id, string $value): array {
    return self::whenReady(static function () use ($userid, $id, $value) {
      return pusyuuAccountStoragePut($userid, 'p-memo', $id, $value);
    }, ['ok' => false, 'message' => self::unavailableReason()]);
  }

  public static function storageDelete(string $userid, string $id): array {
    return self::whenReady(static function () use ($userid, $id) {
      return pusyuuAccountStorageDelete($userid, 'p-memo', $id);
    }, ['ok' => false, 'message' => self::unavailableReason()]);
  }
}

// ---------------------------------------------------------------------
// メモ本体の保存/一覧/取得/削除。
//
// メモ本体は、以前は自分自身(このディレクトリのstorage/)の暗号化JSONLファイル
// (1ユーザー1ファイルに全メモをまとめる方式。account.jsonl旧userDataと同じ
// 「誰か1件編集するだけで自分の全メモぶんを読み書きする」設計)に持っていたが、
// 共通ストレージ(p-drive)へ移行した(1メモ=1項目=1ファイルなので、1件の編集が
// 他のメモの読み書きを巻き込まない)。
//
// 保存先の実体はメイキィの中にあるので、共通クライアントの driveGet/drivePut 等で
// メイキィへ直接話します。以前は p-drive.pusyuuwanko.com へ投げて、p-driveが中身を
// 見ずにメイキィへ横流しする取次を挟んでいましたが、実体がメイキィにある以上その
// 一段は無駄で、同じHTTPの配管がp-memoの中に二重に存在するだけだったため廃止しました
// (p-driveは「マイファイル」画面だけを担当します)。
// ---------------------------------------------------------------------
//
// pmemoCurrentUser() が返す userid(p-meikieeのsha256ハッシュ)を、p-drive上の
// useridキーとしてそのまま使う。
//
// 旧ストレージからp-driveへの移行コードは、全ユーザーの移行が済んだのを確認して
// 撤去した(推測不能なランダム名のディレクトリに置いていた暗号化JSONLと、それを
// 読むための復号処理一式)。このファイルの中でメモ本体をローカルに読み書きする
// 経路はもう1つも無く、保存先はp-drive(実体はメイキィ)だけ。もし今後
// 「p-memoのディレクトリ内にメモが入っている」前提のコードを見かけたら、
// それは移行前の名残りなので信用しないこと。
// ---------------------------------------------------------------------

const PMEMO_MEMO_MAX_BYTES = 1048576; // 1MiB
const PMEMO_MEMO_MAX_COUNT = 100;
const PMEMO_MEMO_TITLE_MAX = 120;

/** 本文を除いた一覧用の情報にします(一覧取得で全メモの本文を返すとレスポンスが肥大化するため)。 */
function pmemoMemoSummary(array $memo): array {
  $summary = [
    'id'         => (string)($memo['id'] ?? ''),
    'title'      => (string)($memo['title'] ?? ''),
    'charset'    => (string)($memo['charset'] ?? 'UTF-8'),
    'eol'        => (string)($memo['eol'] ?? 'LF'),
    'bytes'      => (int)($memo['bytes'] ?? 0),
    'chars'      => (int)($memo['chars'] ?? 0),
    'created_at' => (int)($memo['created_at'] ?? 0),
    'updated_at' => (int)($memo['updated_at'] ?? 0),
  ];

  return $summary;
}

/** p-drive上のそのユーザーの全メモ(復号済み、生のレコード形)を返す。 */
function pmemoAllMemos(string $userid): array {
  $res = PmemoAccount::storageList($userid);
  $memos = [];

  if (!empty($res['ok']) && is_array($res['items'] ?? null)) {
    foreach ($res['items'] as $item) {
      $decoded = json_decode((string)($item['value'] ?? ''), true);
      if (is_array($decoded)) {
        $memos[] = $decoded;
      }
    }
  }

  return $memos;
}

function pmemoListMemos(string $userid): array {
  $list = array_map('pmemoMemoSummary', pmemoAllMemos($userid));
  usort($list, static fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);

  return $list;
}

/** 見つかれば ['id','title','body','charset','eol',...] を含む連想配列、無ければ null。 */
function pmemoGetMemo(string $userid, string $id): ?array {
  if ($id === '') {
    $res = ['ok' => false];
  } else {
    $res = PmemoAccount::storageGet($userid, $id);
  }

  $memo = (empty($res['ok']) || empty($res['found']))
    ? null
    : json_decode((string)($res['value'] ?? ''), true);

  if (is_array($memo)) {
    $found = pmemoMemoSummary($memo) + ['body' => (string)($memo['body'] ?? '')];
  } else {
    $found = null;
  }

  return $found;
}

/**
 * 新規保存/上書き保存の両方をこの1関数で行います($idが既存メモのidと一致すれば上書き)。
 * 戻り値: ['ok'=>true,'id'=>...,'updated_at'=>...] または ['ok'=>false,'error'=>...,'message'=>...]
 */
function pmemoSaveMemo(string $userid, string $id, string $title, string $body, string $charset, string $eol): array {
  if (strlen($body) > PMEMO_MEMO_MAX_BYTES) {
    $result = ['ok' => false, 'error' => 'too_large', 'message' => '1件あたりの上限（' . number_format(PMEMO_MEMO_MAX_BYTES / 1024) . 'KB）を超えています。内容を分けて保存してください。'];
  } else {
    // 壊れたUTF-8をそのまま保存すると json_encode が丸ごと失敗してファイルが空になるため、保存前に整えます。
    if (!mb_check_encoding($body, 'UTF-8')) {
      $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
    }

    // 題名の決め方。指定があればそれ、無ければ本文の冒頭、本文も空なら「無題のメモ」。
    $given = trim($title);
    if ($given !== '') {
      $title = $given;
    } else {
      $fromBody = mb_substr(trim(preg_replace('/\s+/u', ' ', $body) ?? ''), 0, 30);
      $title = ($fromBody !== '') ? $fromBody : '無題のメモ';
    }
    $title = mb_substr($title, 0, PMEMO_MEMO_TITLE_MAX);

    $now = time();

    // 上書きか新規か。$idが指定されていても、実際にそのidのメモが無ければ
    // (元の実装と同じく)新規保存として扱う。作成日時は上書きのときだけ引き継ぐ。
    if ($id === '') {
      $createdAt = $now;
    } else {
      $existingRes = PmemoAccount::storageGet($userid, $id);
      if (!empty($existingRes['ok']) && !empty($existingRes['found'])) {
        $existing = json_decode((string)($existingRes['value'] ?? ''), true);
        $createdAt = is_array($existing) ? (int)($existing['created_at'] ?? $now) : $now;
      } else {
        $id = '';
        $createdAt = $now;
      }
    }

    // 件数の上限は新規のときだけ見ます。上書きは増えないので数えても意味がありません。
    $tooMany = ($id === '') && (count(pmemoAllMemos($userid)) >= PMEMO_MEMO_MAX_COUNT);

    if ($tooMany) {
      $result = ['ok' => false, 'error' => 'quota_exceeded', 'message' => '保存できるメモは' . PMEMO_MEMO_MAX_COUNT . '件までです。不要なメモを削除してから保存してください。'];
    } else {
      if ($id === '') {
        $id = bin2hex(random_bytes(8));
      }

      $record = [
        'id' => $id, 'title' => $title, 'body' => $body, 'charset' => $charset, 'eol' => $eol,
        'bytes' => strlen($body), 'chars' => mb_strlen($body), 'created_at' => $createdAt, 'updated_at' => $now,
      ];
      $putRes = PmemoAccount::storagePut($userid, $id, json_encode($record, JSON_UNESCAPED_UNICODE));

      if (!empty($putRes['ok'])) {
        $result = ['ok' => true, 'id' => $id, 'updated_at' => $now];
      } else if (($putRes['error'] ?? '') === 'quota_exceeded') {
        // 容量超過だけは、利用者が自分で解決できるので理由をそのまま伝えます。
        $result = ['ok' => false, 'error' => 'quota_exceeded', 'message' => '保存容量の上限に達しています。不要なメモを削除してから保存してください。'];
      } else {
        $result = ['ok' => false, 'error' => 'storage_error', 'message' => 'メモの保存に失敗しました。しばらくしてからもう一度お試しください。'];
      }
    }
  }

  return $result;
}

function pmemoDeleteMemo(string $userid, string $id): array {
  if ($id === '') {
    $result = ['ok' => false, 'error' => 'invalid_input', 'message' => '削除するメモが指定されていません。'];
  } else {
    $existing = PmemoAccount::storageGet($userid, $id);

    if (empty($existing['ok']) || empty($existing['found'])) {
      $result = ['ok' => false, 'error' => 'not_found', 'message' => '指定されたメモが見つかりませんでした。'];
    } else {
      $res = PmemoAccount::storageDelete($userid, $id);

      if (!empty($res['ok'])) {
        $result = ['ok' => true];
      } else {
        $result = ['ok' => false, 'error' => 'storage_error', 'message' => 'メモの削除に失敗しました。しばらくしてからもう一度お試しください。'];
      }
    }
  }

  return $result;
}

// ---------------------------------------------------------------------
// ログイン状態（このサイト自身のセッションで保持します）
//
// 他サイトとCookieを共有しているわけではありません。p-memo は自分のセッションに
// APIから受け取ったトークンを持つだけなので、php.ini の session.cookie_domain を
// サブドメイン共有に書き換えるといったサーバ設定の変更は不要です。
//
// メモ本体の保存場所を変えたことで、以前あったメモ保存API専用のトークン
// ($_SESSION['pusyuu_token'])は不要になりました。pmemoCurrentUser() が返す
// userid を、そのままp-drive上の保存先キーとして使います。
// ---------------------------------------------------------------------

/** 本人確認(accountsサービス)に使うトークン。 */
function pmemoAccountsToken() {
  return isset($_SESSION['pusyuu_accounts_token']) ? $_SESSION['pusyuu_accounts_token'] : '';
}

/**
 * ログイン中ならユーザー情報（userid / username / name）、そうでなければ null。
 * 1リクエスト中に何度呼ばれてもAPIアクセスは1回だけです。
 */
function pmemoCurrentUser() {
  static $checked = false;
  static $user = null;

  // 2回目以降は $checked が true なので、この塊ごと素通りして控えをそのまま返します。
  if ($checked === false) {
    $checked = true;
    $token = pmemoAccountsToken();

    if ($token === '') {
      $user = null;
    } else {
      $res = PmemoAccount::sessionResult();

      if (!empty($res['ok'])) {
        $user = $res['user'];
      } else {
        // 【失敗しても、いつでも控えを捨ててよいわけではありません】
        // 以前ここは「ok以外なら問答無用でトークンを消す」と書かれていました。その結果、
        // アカウント基盤が一時的に落ちているだけで利用者がログアウト扱いになり、
        // 基盤が復旧しても未ログインのまま、という状態になります。
        // 捨ててよいのは「そのトークンはもう無効だ」と基盤が答えたときだけです。
        // 届かなかっただけのときは、何もせず次の機会を待ちます。
        if (!empty($res['rejected'])) {
          unset($_SESSION['pusyuu_accounts_token']);
        }
        $user = null;
      }
    }
  }

  return $user;
}

function pmemoLogout() {
  $accountsToken = pmemoAccountsToken();
  if ($accountsToken !== '') {
    PmemoAccount::revokeToken($accountsToken);
  }
  unset($_SESSION['pusyuu_accounts_token']);
  unset($_SESSION['pmemo_token']);
  PmemoSession::regenerateId();
}

// ---------------------------------------------------------------------
// CSRFトークン（PIPSの server_token と同じ考え方）
// ---------------------------------------------------------------------

function pmemoCsrfToken() {
  if (empty($_SESSION['pmemo_token'])) {
    $_SESSION['pmemo_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['pmemo_token'];
}

/**
 * 単純な == 比較はタイミング攻撃に弱いため hash_equals を使います。
 * 配列で送られてきた場合に hash_equals が TypeError で落ちるので is_string も見ます。
 */
function pmemoCsrfIsValid() {
  $valid = isset($_POST['pmemo_token'])
    && is_string($_POST['pmemo_token'])
    && !empty($_SESSION['pmemo_token'])
    && hash_equals($_SESSION['pmemo_token'], $_POST['pmemo_token']);

  return $valid;
}

// ---------------------------------------------------------------------
// 文字コード・改行コードの変換
//
// ここがサーバ（PHP）でしかできない部分です。ブラウザのJavaScriptにある
// TextEncoder はUTF-8しか出力できないため、Shift_JISやEUC-JPでの保存は
// JSだけでは実現できません。
// ---------------------------------------------------------------------

/**
 * 画面に出す選択肢と、mbstringに渡す実際のエンコーディング名の対応表。
 * Windows系の機種依存文字（①や～など）を落とさないよう、Shift_JISはCP932相当の
 * SJIS-win、EUC-JPは eucJP-win を使います。
 */
function pmemoCharsets() {
  $charsets = [
    'UTF-8'      => ['label' => 'UTF-8（推奨）',            'mb' => 'UTF-8',        'bom' => false],
    'UTF-8-BOM'  => ['label' => 'UTF-8（BOM付き）',         'mb' => 'UTF-8',        'bom' => true],
    'Shift_JIS'  => ['label' => 'Shift_JIS（Windows標準）', 'mb' => 'SJIS-win',     'bom' => false],
    'EUC-JP'     => ['label' => 'EUC-JP',                   'mb' => 'eucJP-win',    'bom' => false],
    'ISO-2022-JP'=> ['label' => 'ISO-2022-JP（JISコード）', 'mb' => 'ISO-2022-JP',  'bom' => false],
    'ASCII'      => ['label' => 'ASCII（英数字のみ）',      'mb' => 'ASCII',        'bom' => false],
  ];

  return $charsets;
}

function pmemoEols() {
  $eols = [
    'LF'   => ['label' => 'LF（Linux / macOS）',    'value' => "\n"],
    'CRLF' => ['label' => 'CRLF（Windows）',        'value' => "\r\n"],
    'CR'   => ['label' => 'CR（クラシックMac）',    'value' => "\r"],
  ];

  return $eols;
}

// 文字の拡大縮小（表示上のfont-sizeのみで、保存内容には影響しません）。
// エディタの本文などとは性質が違う（同じメモを別端末で開いても揃える必要が
// 無い、見た目だけの好み）ため、stashEditorの対象($_SESSION['pmemo_editor'])
// には混ぜず、別のセッションキーで独立して持たせています。
const PMEMO_FONT_SIZE_MIN = 8;
const PMEMO_FONT_SIZE_MAX = 48;
const PMEMO_FONT_SIZE_DEFAULT = 16;
const PMEMO_FONT_SIZE_STEP = 2;

function pmemoFontSize(): int {
  $size = isset($_SESSION['pmemo_font_size']) ? (int)$_SESSION['pmemo_font_size'] : PMEMO_FONT_SIZE_DEFAULT;
  return max(PMEMO_FONT_SIZE_MIN, min(PMEMO_FONT_SIZE_MAX, $size));
}

/**
 * 改行コードを揃えます。いったん全部LFへ寄せてから目的の形に置き換えます。
 */
function pmemoConvertEol($text, $eolKey) {
  $eols = pmemoEols();

  if (!isset($eols[$eolKey])) {
    // 知らない指定。勝手に直さず、そのまま返します。
    $converted = $text;
  } else {
    // いったん全部LFへ寄せてから目的の形に置き換えます。
    // 直接CRLFへ置き換えると、既にCRLFの箇所が CRCRLF になります。
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $converted = ($eolKey === 'LF') ? $normalized : str_replace("\n", $eols[$eolKey]['value'], $normalized);
  }

  return $converted;
}

/**
 * UTF-8の文字列を指定の文字コードへ変換します。
 *
 * 戻り値: [
 *   'data' => 変換後のバイト列,
 *   'lost' => その文字コードでは表現できず「?」に置き換わった文字の一覧（最大20件）,
 *   'lossy'=> 表現できない文字が1つでもあったか,
 * ]
 *
 * 「保存したら文字が化けていた」を保存前に検知して伝えられるのは、
 * 実際にそのエンコーディングへ変換してみることができるサーバ側だけです。
 */
function pmemoConvertCharset($text, $charsetKey) {
  $charsets = pmemoCharsets();
  // 知らない指定はUTF-8として扱います(変換できないまま進むとバイト列が壊れるため)。
  $charsetKey = isset($charsets[$charsetKey]) ? $charsetKey : 'UTF-8';
  $target  = $charsets[$charsetKey]['mb'];
  $withBom = $charsets[$charsetKey]['bom'];

  if ($target === 'UTF-8') {
    // 元からUTF-8なので変換は不要。BOMを付けるかどうかだけの違いです。
    $result = [
      'data'  => ($withBom ? "\xEF\xBB\xBF" : '') . $text,
      'lost'  => [],
      'lossy' => false,
    ];
  } else {
    $previous = mb_substitute_character();
    mb_substitute_character(0x3F); // 表現できない文字は「?」にします

    $converted = mb_convert_encoding($text, $target, 'UTF-8');
    $roundTrip = mb_convert_encoding($converted, 'UTF-8', $target);
    $lossy = ($roundTrip !== $text);
    $lost = [];

    // どの文字が失われたのかを1文字ずつ調べます。長文で毎回やると重いので、
    // 実際に欠落があったときだけ、かつ現実的なサイズのときだけ実行します。
    if ($lossy && strlen($text) <= 200000) {
      $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
      if (is_array($chars)) {
        foreach ($chars as $char) {
          // 元から「?」だった文字は、置き換わったのか元々そうなのか区別できないので数えません。
          if ($char !== '?') {
            $back = mb_convert_encoding(mb_convert_encoding($char, $target, 'UTF-8'), 'UTF-8', $target);
            if ($back !== $char && !in_array($char, $lost, true)) {
              $lost[] = $char;
              if (count($lost) >= 20) { break; }
            }
          }
        }
      }
    }

    // 呼ばれる前の設定へ必ず戻します。戻さないと他の変換にまで影響します。
    mb_substitute_character($previous);
    $result = ['data' => $converted, 'lost' => $lost, 'lossy' => $lossy];
  }

  return $result;
}

/**
 * 読み込まれたファイルの文字コードを推測し、UTF-8にして返します。
 * ブラウザのFileReaderはUTF-8決め打ちで読むため、Windows標準のメモ帳で書いた
 * Shift_JISのtxtが化けます。サーバ側なら mb_detect_encoding で判別できます。
 */
function pmemoDetectAndConvertToUtf8($bytes) {
  $order = ['ASCII', 'UTF-8', 'eucJP-win', 'SJIS-win', 'ISO-2022-JP'];
  // BOMが付いていれば判定するまでもないので、そのときは推測を走らせません。
  $hasBom = (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0);
  $detected = $hasBom ? false : mb_detect_encoding($bytes, $order, true);

  if ($hasBom) {
    // BOM付きUTF-8はBOMを取り除くだけで確定します
    $result = ['text' => substr($bytes, 3), 'charset' => 'UTF-8-BOM'];
  } else if ($detected === false || $detected === 'ASCII' || $detected === 'UTF-8') {
    // 判別できなかった場合もUTF-8として扱います(勝手に変換すると化けるため、そのまま返す)。
    $result = ['text' => $bytes, 'charset' => 'UTF-8'];
  } else {
    $labels = ['eucJP-win' => 'EUC-JP', 'SJIS-win' => 'Shift_JIS', 'ISO-2022-JP' => 'ISO-2022-JP'];
    $result = [
      'text'    => mb_convert_encoding($bytes, 'UTF-8', $detected),
      'charset' => isset($labels[$detected]) ? $labels[$detected] : $detected,
    ];
  }

  return $result;
}

// ---------------------------------------------------------------------
// ダウンロード用ファイル名の組み立て
// ---------------------------------------------------------------------

/**
 * ファイル名として使えない文字を弾きます（Windowsの禁則文字と制御文字）。
 */
function pmemoSanitizeFileName($name) {
  // 不正なUTF-8が混ざっていると /u 付きの preg_replace が null を返してしまうので先に整えます
  if (!mb_check_encoding($name, 'UTF-8')) {
    $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
  }
  $name = preg_replace('/[\/\\\\:*?"<>|]/u', '', $name);
  $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
  $name = is_string($name) ? trim($name) : '';

  // パスを遡られないように、先頭のドットだけの名前も避けます
  if ($name === '' || $name === '.' || $name === '..') {
    $safe = '';
  } else {
    $safe = $name;
  }

  return $safe;
}

/**
 * Content-Disposition ヘッダを組み立てます。
 *
 * 古いブラウザは RFC 5987 の filename* を理解しないので、必ず素の filename も併記します。
 * さらにIE/古いEdgeは filename にパーセントエンコードしたUTF-8を入れると正しく戻せるため、
 * User-Agentを見てそちらを使い分けます。
 */
function pmemoContentDisposition($fileName) {
  $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
  $isLegacyIe = (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false);

  $encoded = rawurlencode($fileName);

  if ($isLegacyIe) {
    // IE/古いEdgeは filename* を理解しない代わりに、filename にパーセント
    // エンコードしたUTF-8を入れると正しく戻せます。
    $header = 'attachment; filename="' . $encoded . '"';
  } else {
    // 日本語などをASCIIに落とした保険用の名前。潰れて空になったら汎用名にします。
    $translit = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fileName) : '';
    $ascii = is_string($translit) ? $translit : '';
    $ascii = trim(preg_replace('/[^A-Za-z0-9._-]/', '_', $ascii), '_');

    if ($ascii === '' || $ascii === '.') {
      $ext = pathinfo($fileName, PATHINFO_EXTENSION);
      $ascii = 'p-memo' . ($ext !== '' ? '.' . preg_replace('/[^A-Za-z0-9]/', '', $ext) : '.txt');
    }

    // 素の filename も必ず併記します。filename* しか書かないと、
    // RFC 5987 を知らないブラウザがファイル名を決められません。
    $header = 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . $encoded;
  }

  return $header;
}

// ---------------------------------------------------------------------
// ダウンロードのPOST→リダイレクト→GET化
//
// action=download は長らくPOSTへの応答として直接ファイルを返していましたが、
// 一部の古いモバイルブラウザ（特にChromeではない古いAndroid標準ブラウザ）は
// POSTのレスポンスをダウンロードマネージャに正しく渡せないことがあり、
// 読み込み中の表示のまま何も保存されない不具合が起きます。
// このアプリの他の全操作（保存以外）はPOST→リダイレクト→GETの形を取っているため、
// それに合わせて、生成したファイルをいったん短命の一時ファイルに置き、
// GETのURLへリダイレクトしてそこから配信するようにします。
// ---------------------------------------------------------------------

const PMEMO_DOWNLOAD_DIR = '../.pusyuuHiddenFiles/p-memo_downloads'; // 自分自身に隠しファイルを作らない、理由は公開フォルダな為、一段戻る事
const PMEMO_DOWNLOAD_TTL = 300; // 5分。取りに来られなかった古い一時ファイルを掃除する目安

/**
 * 一度配信したあと、同じトークンをもう何秒間だけ受け付けるか。
 *
 * 【なぜ即座に消してはいけないか】
 * 以前ここは「配信したらその場で一時ファイルを削除する」でした。使い回しを防ぐ意図は
 * 正しいのですが、そのせいで「ダウンロードしたらフォームやHTMLが保存される」という
 * 不具合が起きていました。
 *
 * 仕組みはこうです。?download= のURLへ進むとブラウザは octet-stream を受け取り、
 * そこから先をダウンロードマネージャへ引き継ぎます。ところがダウンロードマネージャは、
 * 同じURLをもう一度取りに行くことがあります(ブラウザの先読みや、利用者の再読み込みでも
 * 同じことが起きます)。1回目で一時ファイルを消してしまうと、2回目には何も残っていない
 * ので、こちらはエディタ画面への302を返します。ダウンロードマネージャはそれを追いかけて
 * **エディタ画面のHTMLをファイルとして保存**します。利用者から見ると、メモのはずの
 * ファイルを開いたらフォームやHTMLが入っている、という状態になります。
 *
 * そこで、配信した時刻を一時ファイル自身に書き込み、この秒数のあいだは同じ中身を
 * 返せるようにしています。使い回しを防ぐ意図は保ったまま(この窓を過ぎれば無効)、
 * 取りに来るのが二度でも壊れません。長くしすぎないこと。
 */
const PMEMO_DOWNLOAD_GRACE = 30;

function pmemoDownloadDir(): string {
  if (!is_dir(PMEMO_DOWNLOAD_DIR)) {
    @mkdir(PMEMO_DOWNLOAD_DIR, 0770, true);
  }
  $htaccess = PMEMO_DOWNLOAD_DIR . '/.htaccess';
  if (is_dir(PMEMO_DOWNLOAD_DIR) && !file_exists($htaccess)) {
    // ダウンロード本体（暗号化はしていません）が直接HTTPで拾えないようにします。
    @file_put_contents($htaccess, "# トークンを知らない限り読めない一時ファイル置き場ですが、念のため直接アクセスも禁止します。\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
  }
  return PMEMO_DOWNLOAD_DIR;
}

/** 取りに来られないまま残った古い一時ファイルを掃除します。 */
function pmemoDownloadCleanup(): void {
  $files = @glob(pmemoDownloadDir() . '/*.json');

  if ($files) {
    $now = time();
    foreach ($files as $file) {
      $mtime = @filemtime($file);
      if ($mtime === false || $now - $mtime > PMEMO_DOWNLOAD_TTL) {
        @unlink($file);
      }
    }
  }
}

/** トークンから一時ファイルのパスを求めます。トークンの形式が不正なら null。 */
function pmemoDownloadTokenPath(string $token): ?string {
  // 形を厳しく見ることが、この置き場へのパス操作を防ぐ唯一の砦です。
  // 「16進48文字」以外を通さないので、../ などは組み立てようがありません。
  if (preg_match('/^[a-f0-9]{48}$/', $token)) {
    $path = pmemoDownloadDir() . '/' . $token . '.json';
  } else {
    $path = null;
  }

  return $path;
}

/** ダウンロード用の一時ファイルを作り、トークンを返します。失敗したら null。 */
function pmemoStoreDownload(string $fileName, string $data): ?string {
  pmemoDownloadCleanup();
  $token = bin2hex(random_bytes(24));
  $path = pmemoDownloadTokenPath($token);
  $payload = json_encode(['fileName' => $fileName, 'data' => base64_encode($data)], JSON_UNESCAPED_UNICODE);

  if ($path !== null && $payload !== false && @file_put_contents($path, $payload) !== false) {
    $result = $token;
  } else {
    $result = null;
  }

  return $result;
}

/**
 * トークンに対応する一時ファイルの中身を取り出します。取り出せなければ null。
 * ファイル名は $fileName に入れて返します。
 *
 * 1回目の取り出しで「配信した時刻」を書き込み、そこから PMEMO_DOWNLOAD_GRACE 秒の
 * あいだは同じ中身を返します。理由は PMEMO_DOWNLOAD_GRACE のコメントを参照。
 * ひとことで言えば、1回目で消してしまうと、取りに来る2回目がエディタ画面のHTMLを
 * ファイルとして保存してしまうためです。
 */
function pmemoTakeDownload(string $token, ?string &$fileName): ?string {
  $fileName = '';
  $path = pmemoDownloadTokenPath($token);

  if ($path === null || !is_file($path)) {
    // トークンの形が不正、または既に無効化されている
    $result = null;
  } else {
    $payload = @file_get_contents($path);
    $info = ($payload !== false) ? json_decode($payload, true) : null;

    if (!is_array($info) || !isset($info['fileName'], $info['data'])) {
      @unlink($path); // 中身が壊れている一時ファイルを残しておく意味はありません
      $result = null;
    } else {
      $now = time();
      // 猶予を過ぎたかどうか。1回目(servedAtが無い)は必ず内側です。
      $expired = isset($info['servedAt']) && ($now - (int)$info['servedAt'] > PMEMO_DOWNLOAD_GRACE);

      if ($expired) {
        @unlink($path);
        $result = null;
      } else {
        if (!isset($info['servedAt'])) {
          // 1回目。配信した時刻を記録します。**ここで消さないのが肝心です**
          // (消すと、取りに来る2回目がエディタ画面のHTMLを保存してしまいます)。
          $info['servedAt'] = $now;
          @file_put_contents($path, json_encode($info, JSON_UNESCAPED_UNICODE));
        }

        $data = base64_decode((string)$info['data'], true);

        if ($data === false) {
          @unlink($path);
          $result = null;
        } else {
          $fileName = (string)$info['fileName'];
          $result = $data;
        }
      }
    }
  }

  return $result;
}

/** ヘッダーを送ってファイルの中身をそのまま出力します（POST直接応答・GET一時配信の両方から使います）。 */
function pmemoEmitDownload(string $fileName, string $data): void {
  PmemoSession::close();
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  // text/plain だと古いブラウザがブラウザ内に表示してしまうことがあるので、
  // 常にダウンロード扱いになる octet-stream で返します。
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: ' . pmemoContentDisposition($fileName));
  header('Content-Length: ' . strlen($data));
  header('Content-Transfer-Encoding: binary');
  // IEはHTTPS + no-cache/no-store 系のヘッダが付いているとダウンロードに失敗するという
  // 有名な不具合があるため、private / must-revalidate を使います。
  header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
  header('Pragma: public');
  header('Expires: 0');
  header('X-Content-Type-Options: nosniff');
  echo $data;
  exit;
}

// =====================================================================
// 無音SSOチェック(Googleのアカウント連携のような、フォーム無しの自動ログイン)
//
// このブラウザがp-meikiee(旧称 accounts.pusyuuwanko.com、現在は
// PUSYUU_ACCOUNTS_HOST 定数の通り p-meikiee.pusyuuwanko.com)に既にログイン
// 済みなら、p-memoでもフォームを出さずに自動でログイン状態にします。
// 本人確認用のaccountsトークン(pusyuu_accounts_token)を$_SESSIONへ保存する
// だけで、メモの保存・読込(pmemoCurrentUser()のuseridをそのままキーに使う)
// まで含めて無音で復元されます。$_SESSION['pusyuu_sso_checked']で
// 1ブラウザセッションにつき1回だけ確認し、無限リダイレクトを防ぎます。
// =====================================================================

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  // action=downloadがPOST後にリダイレクトしてくる、一時ファイルからの配信口です。
  // セッションもSSOも関係ない単純なファイル配信なので、他の処理より先に済ませます。
  if (isset($_GET['download']) && is_string($_GET['download'])) {
    $downloadData = pmemoTakeDownload($_GET['download'], $downloadFileName);
    if ($downloadData !== null) {
      pmemoEmitDownload($downloadFileName, $downloadData);
    }
    // トークンが不正・期限切れ・猶予も過ぎている場合はエディタへ戻します。
    backToEditor('ダウンロードの準備がまだできていないか、既に完了しています。お手数ですがもう一度保存し直してください。');
  }

}

// ログインの往復の手順そのものは全プロダクト共通なので、共有スクリプトに任せます。
// p-memo固有なのは「ログイン中かどうかの判定」と「受け取ったトークンを自分の
// セッションのどのキーへ入れるか」の2点だけなので、その2つだけを渡します。
//
// 【function_exists() を外さないこと】共有スクリプトが無い環境ではこの関数自体が
// 存在せず、致命的エラーでp-memoのページが1行も表示されなくなります。
// なお、基盤へ届かないときにブラウザを送り出さない判断は共有スクリプト側が行います
// (送り出すと利用者はブラウザの「アクセスできません」を見ることになり、
//  p-memoのページは一度も描画されません)。
if (function_exists('pusyuuAccountHandleSignIn')) {
  pusyuuAccountHandleSignIn(
    static function (): bool {
      return pmemoAccountsToken() !== '';
    },
    static function (array $user, string $token): void {
      PmemoSession::regenerateId(); // ログイン=権限昇格。セッション固定化を防ぐ
      $_SESSION['pusyuu_accounts_token'] = $token;
    }
  );
}

// ログインに失敗していたら、その理由を画面へ出すために受けておきます。
// 拾わないと、失敗は誰にも見えません(判定はリダイレクトの直前で終わり、その
// リクエストはそのまま消えるため、利用者には「押したのに元の画面へ戻っただけ」に
// 見えます)。読めるのは1度だけなので、必ず変数へ受けてから使ってください。
$pmemoSignInError = PmemoAccount::takeSignInError();

$title = "プシューサービス - プシューメモとテキストエディター";
$description = "シンプルなメモ帳アプリですが、このメモアプリには、データの保護、文字コードの選択（UTF-8/Shift_JIS/EUC-JP/ISO-2022-JP）、改行コードの選択、文字カウント、大文字と小文字への変換、削除機能、ファイルの大きさ制限なし、拡張子の制限なし、他のメモ帳のデータも編集可能、プシューメイキィでの端末間同期、と普通のメモ帳でははできないことがこのメモ帳ではご利用いただけます！！";

/**
 * 出力用のエスケープ。長いのでこのファイル内だけの短い名前にしています。
 */
function pmemoH($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/**
 * CSS/JSのURLにファイル更新時刻をクエリとして付けます（キャッシュ対策）。
 *
 * 特にIEは静的ファイルのキャッシュに強く固執し、Cache-Controlの指示があっても
 * 古いバージョンを握ったまま離さないことがあります。ファイルの中身が変わると
 * URLそのものが変わるこの方式なら、ブラウザは「別のファイル」として新しく
 * 取得し直すため、更新のたびに手でバージョン番号を書き換える必要もありません。
 */
function pmemoAssetUrl(string $relativePath): string {
  $absolute = __DIR__ . '/' . ltrim($relativePath, '/.');
  $version = @filemtime($absolute);
  return $relativePath . ($version !== false ? '?v=' . $version : '');
}

/**
 * 処理結果のメッセージを持たせてエディタへ戻します（PRG: POST→リダイレクト→GET）。
 * 戻り先で同じPOSTが再送されるのを防ぐため、必ずリダイレクトを挟みます。
 */
function backToEditor($message = '', $query = '') {
  if ($message !== '') {
    $_SESSION['pmemo_flash'] = $message;
  }
  header('Location: ./' . ($query !== '' ? '?' . $query : '') . '#editor');
  exit;
}

/**
 * エディタの内容をサーバ側にも持っておきます。
 * リダイレクトを挟んでも書きかけの文章が消えないようにするためのもので、
 * これまでlocalStorageだけで行っていた一時保存のサーバ側版でもあります。
 */
function stashEditor($body, $fileName, $charsetKey, $eolKey, array $extra = []) {
  global $memoId, $memoLoadedTitle;
  $_SESSION['pmemo_editor'] = array_merge([
    'body'     => $body,
    'fileName' => $fileName,
    'charset'  => $charsetKey,
    'eol'      => $eolKey,
    // 開いているクラウドメモのID。$extra で明示的に上書きできます。
    'memo_id'  => isset($memoId) ? $memoId : '',
    // memo_idを読み込んだ時点でのタイトル。cloud_save時に現在のfileNameと
    // 比較し、変わっていれば「別のメモ」とみなして新規保存するために使います
    // ($extra で明示的に上書きできます)。
    'memo_loaded_title' => isset($memoLoadedTitle) ? $memoLoadedTitle : '',
    // 「保存できない文字」の警告は毎回作り直します。ここで空にしておかないと
    // 一度警告が出たあと、別の操作をしても警告が出っぱなしになります。
    'lost'     => [],
  ], $extra);
}

// =====================================================================
// POSTの処理
//
// エディタ本体の操作(action=)と、ログイン・ログアウト・クラウドメモの
// 読み込み/削除といった小さな操作(account_action=)を、フィールド名で
// 振り分けます(以前は前者を save.php という別ファイルが受け持っていましたが、
// 入口を1つにまとめる方針のためこのファイルへ統合しました)。
// 処理後は必ずリダイレクトして、リロードで同じ操作が二重に走らないようにします。
// =====================================================================

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {

  // post_max_size を超えると $_POST が丸ごと空になり、エラーも出ないまま処理が失敗します
  if (empty($_POST) && (int)($_SERVER["CONTENT_LENGTH"] ?? 0) > 0) {
    $_SESSION["pmemo_flash"] = "送信データが大きすぎてサーバが受け取れませんでした（受信上限は " . ini_get("post_max_size") . " です）。";
    header("Location: ./");
    exit;
  }

  if (!pmemoCsrfIsValid()) {
    $_SESSION["pmemo_flash"] = "ページを開いてから時間が経ちすぎたため、処理を中止しました。お手数ですがもう一度お試しください。";
    header("Location: ./");
    exit;
  }

  // =====================================================================
  // エディタ本体の操作(以前は save.php が受け持っていました)
  //   action=download            … 文字コード・改行コードを変換してファイルとして返す
  //   action=cloud_save          … プシュー共通アカウントへメモを保存する
  //   action=upload              … ファイルをサーバ側で読み込んでエディタに展開する
  //   action=upper/lower/replace … 大文字化・小文字化・文字置換をサーバ側で行う
  //   action=clear/undo_clear    … エディタの内容を消す/元に戻す
  //
  // 【なぜサーバでやるのか】
  // これまでの保存は showSaveFilePicker / Blob + URL.createObjectURL +
  // a[download] というJavaScriptの機能に頼っていたため、それらを持たない
  // 古いブラウザ（IE、初期のAndroid標準ブラウザなど）では保存ボタンを押しても
  // 何も起きませんでした。普通のフォームPOSTならどんなブラウザでも送信できるので、
  // JavaScriptが一切動かない環境でも確実にダウンロードできます。
  // Shift_JIS/EUC-JP/ISO-2022-JPでの保存、改行コードの変換、表現できない文字の
  // 事前検知、読み込んだファイルの文字コード判別もJavaScriptだけでは実現できず、
  // サーバ側でしか行えません。
  // =====================================================================

  if (isset($_POST["action"])) {
    $action     = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'download';
    $body       = isset($_POST['Memo']) && is_string($_POST['Memo']) ? $_POST['Memo'] : '';
    $charsetKey = isset($_POST['charset']) && is_string($_POST['charset']) ? $_POST['charset'] : 'UTF-8';
    $eolKey     = isset($_POST['eol']) && is_string($_POST['eol']) ? $_POST['eol'] : 'LF';
    $rawName    = isset($_POST['fileName']) && is_string($_POST['fileName']) ? trim($_POST['fileName']) : '';
    // アカウントに保存済みのメモを開いている場合、そのIDを持ち回ります（上書き保存のため）
    $memoId     = isset($_POST['memo_id']) && is_string($_POST['memo_id']) ? $_POST['memo_id'] : '';
    // そのメモを読み込んだ時点でのタイトル。cloud_saveで現在のfileNameと比較するために持ち回ります
    $memoLoadedTitle = isset($_POST['memo_loaded_title']) && is_string($_POST['memo_loaded_title']) ? $_POST['memo_loaded_title'] : '';

    // isset() は関数の戻り値に対しては使えないため array_key_exists() を使います
    if (!array_key_exists($charsetKey, pmemoCharsets())) { $charsetKey = 'UTF-8'; }
    if (!array_key_exists($eolKey, pmemoEols()))         { $eolKey = 'LF'; }

    // 壊れたUTF-8が混ざっていると以降の変換結果が読めなくなるので、入口で整えます
    if ($body !== '' && !mb_check_encoding($body, 'UTF-8')) {
      $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
    }

    // -----------------------------------------------------------------
    // action=upload : ファイルをサーバ側で読み込む
    //
    // ブラウザのFileReader.readAsText()はUTF-8として読むため、Windows標準の
    // メモ帳で作ったShift_JISのtxtを開くと化けます。サーバなら文字コードを
    // 判別してUTF-8へ直してから渡せます。
    // -----------------------------------------------------------------

    if ($action === 'upload') {
      // 読込フォームには文字コードや改行コードの選択欄が無いので、
      // 直前まで選んでいた設定をセッションから引き継ぎます。
      if (isset($_SESSION['pmemo_editor']['charset'])) { $charsetKey = $_SESSION['pmemo_editor']['charset']; }
      if (isset($_SESSION['pmemo_editor']['eol']))     { $eolKey     = $_SESSION['pmemo_editor']['eol']; }

      if (empty($_FILES['memoFile']) || !is_array($_FILES['memoFile'])) {
        backToEditor('ファイルが送信されませんでした。');
      }

      $file = $_FILES['memoFile'];
      $errorCode = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

      if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        backToEditor('ファイルが大きすぎます（サーバの上限は ' . ini_get('upload_max_filesize') . ' です）。');
      }
      if ($errorCode === UPLOAD_ERR_NO_FILE) {
        backToEditor('ファイルが選ばれていません。');
      }
      if ($errorCode !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        backToEditor('ファイルの受け取りに失敗しました。もう一度お試しください。');
      }

      $bytes = file_get_contents($file['tmp_name']);
      @unlink($file['tmp_name']);
      if ($bytes === false) {
        backToEditor('ファイルを読み込めませんでした。');
      }

      $detected = pmemoDetectAndConvertToUtf8($bytes);
      $name = pmemoSanitizeFileName(isset($file['name']) ? (string)$file['name'] : '');

      // 別のファイルを開いた時点で、それまで開いていたクラウドメモとは別物になるのでIDを外します
      stashEditor($detected['text'], $name !== '' ? $name : $rawName, $charsetKey, $eolKey, ['memo_id' => '', 'memo_loaded_title' => '']);

      $note = ($detected['charset'] !== 'UTF-8' && $detected['charset'] !== 'UTF-8-BOM')
        ? '（' . $detected['charset'] . 'として読み込み、UTF-8に変換しました）'
        : '';
      backToEditor('ファイルを読み込みました。' . $note);
    }

    // -----------------------------------------------------------------
    // action=upper / lower / replace / clear : 本文の加工
    //
    // JavaScriptが使えない環境でも、これらの機能が使えるようにするためのものです。
    // -----------------------------------------------------------------

    if ($action === 'upper') {
      // mb_strtoupper は全角のａ→Ａも含めて正しく変換します
      stashEditor(mb_strtoupper($body), $rawName, $charsetKey, $eolKey);
      backToEditor('すべて大文字に変換しました。');
    }

    if ($action === 'lower') {
      stashEditor(mb_strtolower($body), $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
      backToEditor('すべて小文字に変換しました。');
    }

    // -----------------------------------------------------------------
    // action=zoom_in / zoom_out : 文字の拡大縮小
    //
    // これまでJavaScript側の zoomInCanvas/zoomOutCanvas だけの機能で、
    // JavaScriptが動かない環境では対応するボタンごと隠れて使えませんでした。
    // 他の操作と同じく、書きかけの本文を失わないようここでも stashEditor します。
    // -----------------------------------------------------------------

    if ($action === 'zoom_in' || $action === 'zoom_out') {
      $step = $action === 'zoom_in' ? PMEMO_FONT_SIZE_STEP : -PMEMO_FONT_SIZE_STEP;
      $_SESSION['pmemo_font_size'] = max(PMEMO_FONT_SIZE_MIN, min(PMEMO_FONT_SIZE_MAX, pmemoFontSize() + $step));
      stashEditor($body, $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
      backToEditor();
    }

    if ($action === 'replace') {
      $search  = isset($_POST['replaceAllowWord']) && is_string($_POST['replaceAllowWord']) ? $_POST['replaceAllowWord'] : '';
      $replace = isset($_POST['replaceWord']) && is_string($_POST['replaceWord']) ? $_POST['replaceWord'] : '';

      if ($search === '') {
        stashEditor($body, $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
        backToEditor('置換対象の文字が入力されていません、置換対象文字は必須です。');
      }

      $count = 0;
      $body = str_replace($search, $replace, $body, $count);
      stashEditor($body, $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
      backToEditor($count > 0 ? $count . '箇所を置換しました。' : '置換可能な文字が見つかりませんでした。');
    }

    if ($action === 'clear') {
      // 空にするだけです。プシューメイキィに保存済みのメモには触れません。
      // JavaScriptが動く環境では押す前にconfirmで確認しますが、動かない環境では
      // 確認ダイアログを出せないので、消す直前の内容を取っておいて戻せるようにします。
      $_SESSION['pmemo_undo'] = $body;
      stashEditor('', $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
      backToEditor('エディタの内容を消しました。プシューメイキィに保存したメモは残っています。');
    }

    if ($action === 'undo_clear') {
      $restored = isset($_SESSION['pmemo_undo']) && is_string($_SESSION['pmemo_undo']) ? $_SESSION['pmemo_undo'] : '';
      unset($_SESSION['pmemo_undo']);
      if ($restored === '') {
        backToEditor('元に戻せる内容がありませんでした。');
      }
      stashEditor($restored, $rawName, $charsetKey, $eolKey, ['memo_id' => $memoId]);
      backToEditor('削除する前の内容に戻しました。');
    }

    // -----------------------------------------------------------------
    // action=cloud_save : プシュー共通アカウントへ保存する
    // -----------------------------------------------------------------

    if ($action === 'cloud_save') {
      $user = pmemoCurrentUser();
      if ($user === null) {
        stashEditor($body, $rawName, $charsetKey, $eolKey);
        backToEditor('プシューメイキィに保存するにはログインが必要です。');
      }

      // 読み込んだ時点のタイトルから変更されている場合は「別のメモ」として扱い、
      // idを送らないことで新規保存させます（そうしないとidだけで一致判定される
      // memo_save側の仕様上、名前を変えても元のメモが上書きされてしまうため）。
      $isRename = $memoId !== '' && $rawName !== $memoLoadedTitle;
      $idToSave = $isRename ? '' : $memoId;

      $res = pmemoSaveMemo($user['userid'], $idToSave, $rawName, $body, $charsetKey, $eolKey);

      // 保存の成否にかかわらず、書いていた内容は必ず残します
      stashEditor($body, $rawName, $charsetKey, $eolKey, [
        'memo_id'           => !empty($res['ok']) && !empty($res['id']) ? $res['id'] : $memoId,
        'memo_loaded_title' => !empty($res['ok']) ? $rawName : $memoLoadedTitle,
      ]);

      if (empty($res['ok'])) {
        backToEditor(isset($res['message']) ? $res['message'] : 'プシューメイキィへの保存に失敗しました。');
      }
      backToEditor($isRename
        ? '名前が変更されていたため、元のメモは残したまま「' . $rawName . '」として新しく保存しました。'
        : 'プシューメイキィに保存しました。他の端末からログインすると同じ内容を開けます。');
    }

    // -----------------------------------------------------------------
    // action=download : 変換してファイルとして返す(それ以外の未知のactionもここに落ちます)
    // -----------------------------------------------------------------

    // 「表現できない文字がある」と警告したうえで、それでも保存すると選ばれた場合に立ちます
    $forced = !empty($_POST['force_lossy']);

    $fileName = pmemoSanitizeFileName($rawName);
    if ($rawName !== '' && $fileName === '') {
      stashEditor($body, '', $charsetKey, $eolKey);
      backToEditor('ファイル名に使用できない文字（ / \\ : * ? " < > | ）が含まれています。ファイル名を入れ直してください。');
    }

    if ($fileName === '') {
      $fileName = 'P-memo_' . date('Ymd_His') . '.txt';
    } elseif (pathinfo($fileName, PATHINFO_EXTENSION) === '') {
      // 拡張子が無い場合は .txt を付けます。
      // （JavaScriptが動く環境では送信前にconfirmで確認しますが、ここはJS無しでも通る経路です）
      $fileName .= '.txt';
    }

    $converted = pmemoConvertEol($body, $eolKey);
    $result = pmemoConvertCharset($converted, $charsetKey);

    // 選んだ文字コードで表現できない文字があった場合、黙って「?」に潰さず、
    // 何が消えるのかを見せてから決めてもらいます。
    // 実際にそのエンコーディングへ変換してみないと分からないので、サーバ側だからできる確認です。
    if ($result['lossy'] && !$forced) {
      stashEditor($body, $rawName, $charsetKey, $eolKey, ['lost' => $result['lost']]);
      backToEditor('', 'warn=lossy');
    }

    $data = $result['data'];

    // 書きかけの内容はダウンロード後も残しておきます
    stashEditor($body, $rawName, $charsetKey, $eolKey);

    // POSTへ直接応答する形だと、古いAndroid標準ブラウザ等のダウンロードマネージャが
    // うまく受け取れないことがあるため、一時ファイルに置いてGETのURLへリダイレクトします。
    // 一時ファイルを作れなかった場合だけ、これまで通りその場で返します（保険のフォールバック）。
    $downloadToken = pmemoStoreDownload($fileName, $data);
    if ($downloadToken !== null) {
      header('Location: ./?download=' . $downloadToken);
      exit;
    }
    pmemoEmitDownload($fileName, $data);
  }

  $postAction = isset($_POST["account_action"]) && is_string($_POST["account_action"]) ? $_POST["account_action"] : "";

  if ($postAction === "logout") {
    pmemoLogout();
    $_SESSION["pmemo_flash"] = "ログアウトしました。エディタに書いた内容はこの端末に残っています。";
    // p-memo自身のトークンを失効させるだけでは、accounts自身のログイン状態
    // (無音SSOが見ているセッションクッキー)が生きたままになり、次のページ
    // 読み込みで無音SSOが自動的に再ログインさせてしまう。accountsのログアウト
    // 画面を一瞬経由してからここへ戻ることで、accounts側のログイン状態も
    // 一緒に終了させる。
    header("Location: " . PmemoAccount::logoutUrl());
    exit;
  }

  if ($postAction === "cloud_load") {
    $loadUser = pmemoCurrentUser();
    $memo = $loadUser !== null ? pmemoGetMemo($loadUser["userid"], isset($_POST["memo_id"]) ? $_POST["memo_id"] : "") : null;
    if ($memo !== null) {
      $loadedTitle = isset($memo["title"]) ? $memo["title"] : "";
      $_SESSION["pmemo_editor"] = [
        "body"     => isset($memo["body"]) ? $memo["body"] : "",
        "fileName" => $loadedTitle,
        "charset"  => isset($memo["charset"]) ? $memo["charset"] : "UTF-8",
        "eol"      => isset($memo["eol"]) ? $memo["eol"] : "LF",
        "memo_id"  => isset($memo["id"]) ? $memo["id"] : "",
        // 保存時にfileNameと比較して、別名保存かどうかを判定するために覚えておきます
        "memo_loaded_title" => $loadedTitle,
      ];
      $_SESSION["pmemo_flash"] = "プシューメイキィに保存したメモを読み込みました。";
    } else {
      $_SESSION["pmemo_flash"] = $loadUser === null ? "プシューメイキィに保存したメモを読み込むにはログインが必要です。" : "指定されたメモが見つかりませんでした。";
    }
    header("Location: ./#editor");
    exit;
  }

  if ($postAction === "cloud_delete") {
    $deleteUser = pmemoCurrentUser();
    $res = $deleteUser !== null
      ? pmemoDeleteMemo($deleteUser["userid"], isset($_POST["memo_id"]) ? $_POST["memo_id"] : "")
      : ["ok" => false, "message" => "プシューメイキィ上のメモを削除するにはログインが必要です。"];
    $_SESSION["pmemo_flash"] = !empty($res["ok"])
      ? "プシューメイキィ上のメモを削除しました。"
      : (isset($res["message"]) ? $res["message"] : "メモの削除に失敗しました。");
    header("Location: ./#cloud");
    exit;
  }

  header("Location: ./");
  exit;
}

// =====================================================================
// 表示用データの用意
// =====================================================================

$currentUser = pmemoCurrentUser();
$csrfToken   = pmemoCsrfToken();

// 直前の操作結果のメッセージ（1回表示したら消します）
$flash = isset($_SESSION["pmemo_flash"]) ? $_SESSION["pmemo_flash"] : "";
unset($_SESSION["pmemo_flash"]);

// エディタの状態。サーバ側にも持っておくことで、リダイレクトを挟んでも
// 書きかけの文章が消えません（localStorageによる一時保存のサーバ側版です）。
$editorDefaults = ["body" => "", "fileName" => "", "charset" => "UTF-8", "eol" => "LF", "memo_id" => "", "memo_loaded_title" => "", "lost" => []];
$editor = isset($_SESSION["pmemo_editor"]) && is_array($_SESSION["pmemo_editor"])
  ? array_merge($editorDefaults, $_SESSION["pmemo_editor"])
  : $editorDefaults;

// 「削除」を取り消せるかどうか。
// JavaScriptが動かない環境では削除前の確認ダイアログが出せないので、
// エディタが空になっている間だけ、戻すためのボタンを出します。
$canUndoClear = ($editor["body"] === "" && !empty($_SESSION["pmemo_undo"]));

// 選んだ文字コードでは表現できない文字があったときの警告
if (!is_array($editor["lost"])) { $editor["lost"] = []; }
$lossyWarning = (isset($_GET["warn"]) && $_GET["warn"] === "lossy" && !empty($editor["lost"]));

// アカウントに保存済みのメモ一覧
$cloudMemos = [];
$cloudError = "";
if ($currentUser !== null) {
  $cloudMemos = pmemoListMemos($currentUser["userid"]);
}

// プロダクト一覧（プシューサービス共通のリンク集）。台帳(pusyuu_registry)から受け取り、
// 自分(台帳のID p-memo)は外します。
// 【自分を URL で見分けないこと】以前は今のホスト名と一覧のURLを突き合わせていたため、
// ドメインや入口が変わると自分が一覧に混ざりました。
// 【__DIR__ を使わないこと】このファイルは入口(実行されるスクリプトそのもの)なので、
// PHPがカレントディレクトリをこのフォルダにしてくれます。素の相対パスで足ります。
$directry = "./../main/pusyuusystem/scripts/php_scripts/pusyuu_registry_client.php";
if (file_exists($directry)) {
  require_once($directry);
}
$productLinks = class_exists('PusyuuRegistryClient') ? PusyuuRegistryClient::links('p-memo') : null;
if ($productLinks === null) {
  $setProductData = "プロダクト一覧を読み込む際に問題が発生しました、データを取得できませんでした。しばらく立ってからリロードしてみてください。";
} else if ($productLinks === []) {
  $setProductData = "現在、表示できるプロダクトは無いようです。";
} else {
  $setProductData = "";
  $fallbackImage = (string)PusyuuRegistryClient::url('main', '/pusyuusystem/images/avater.jpg');
  foreach ($productLinks as $product) {
    $isImage = ($product["image"] !== '') ? $product["image"] : $fallbackImage;
    $setProductData .= '
        <div class="draggable-item">
          <a class="app_link" href="' . pmemoH($product['url']) . '" title="' . pmemoH($product['title']) . '" target="_blank">
            <div class="app_wrapper" draggable="true">
              <div class="app_box-1"><img src="' . pmemoH($isImage) . '" alt="image of app" width="100%" height="100%"></div>
              <p class="app_name">' . pmemoH($product['title']) . '</p>
            </div>
          </a>
        </div>
      ';
  }
}
?>
<!DOCTYPE html>
<!--
  no-js クラスは memo.js が読み込まれた時点で js に置き換わります。
  JavaScriptが動かない環境では no-js のままなので、CSSがそれを見て
  「JSでしか動かないボタン」を隠し、置換フォームをモーダルではなく
  そのまま並べて表示します。
-->
<html lang="ja" class="no-js">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=6.0, minimum-scale=1.0" />
    <meta name="copyright" content="© 2020-2025 created by PusyuuWanko/" />
    <meta name="description" content="<?php echo pmemoH($description); ?>" />
    <meta name="keywords" content="プシューサービス,PusyuuServuce,pusyuu service,プシューメモ,PusyuuMemo,プシュー,Pusyuu,Memo,memo,データの保護,文字コード変換,Shift_JIS,EUC-JP,改行コード変換,文字カウント,大文字と小文字への変換,削除機能,ファイルの大きさ制限なし,拡張子の制限なし,他のメモ帳のデータも編集可能,端末間同期" />
    <meta property="og:title" content="<?php echo pmemoH($title); ?>" />
    <meta property="og:site_name" content="<?php echo pmemoH($title); ?>" />
    <meta property="og:description" content="<?php echo pmemoH($description); ?>" />
    <meta property="og:image" content="<?= pmemoSiteBase('main', true) ?>/pusyuusystem/images/NanimoyakunitatanaiPage.png" />
    <meta property="og:type" content="application" />
    <link rel="manifest" href="./assets/documents/manifest.json" />
    <title><?php echo pmemoH($title); ?></title>
    <link rel="shortcut icon" href="<?= pmemoSiteBase('main') ?>/pusyuusystem/images/favicon.ico" />
    <link href="<?php echo pmemoH(pmemoAssetUrl('./assets/styles/memo.css')); ?>" rel="stylesheet" />
    <!-- privacy_notice.js の script タグより前に置く -->
    <script>
      window.PusyuuPrivacyNoticeConfig = {
        // ---- 機能1(初回アクセス時の自動お知らせ)のカスタマイズ ----
        title: "プライバシーに関するお知らせ",
        // bodyHtml: "<p>...</p>", // 本文を丸ごと差し替えたい場合のみ指定(指定すると下のserviceNameは使われなくなる)
        serviceName: "プシューサービス", // 省略時は特定サービス名を出さず一般論の文章になる
        closeButtonText: "同意する",
        hostsIntroText: "このページ読み込み時に検出した外部の通信先：",
        hostsNoneText: "このページ読み込み時には、外部への通信は検出されませんでした。",
        storageKey: "pusyuu_privacy_notice_dismissed", // 表示済みフラグの保存キー名

        // ---- "js-dependency"プリセット(機能2)のカスタマイズ ----
        jsDependency: {
          level: "none", // このページの実情に合わせて "none" | "low" | "medium" | "high"
          fallbackNote:
            '詳しい方針は<a href="<?= pmemoSiteBase('main') ?>/rules/javascript/" ' +
            'target="_blank">JavaScriptの取り扱いに関する方針</a>をご覧ください。'
        },

        triggerCloseButtonText: "閉じる" // 機能2のモーダルの閉じるボタン文言
      };
    </script>
    <script src="<?= pmemoSiteBase('main') ?>/pusyuusystem/scripts/privacy_notice.js"></script>
    <script src="<?= pmemoSiteBase('main') ?>/pusyuusystem/scripts/request-indicator.js" data-include-async="false"></script>
    <!--
       *----------------------------------
       |  ThisPageVersion: 2.0.0       |
       |  © 2021-2025 By ISAMI ABE     |
       |  LastUpdate: 2026-07-29       |
       |  License: MIT License         |
       |  PusyuuMemoNote               |
     ----------------------------------*

       エディタ本体はPHPが出力しています。JavaScriptが無くても、
       入力・保存（ダウンロード）・読込・置換・アカウント同期のすべてが動きます。
       memo.js は文字数のリアルタイム表示や一時保存といった「あると快適」な部分だけを足します。
    -->
  </head>
  <body>
    <header>
      <h1>P-m</h1>
      <nav>
        <ul>
          <li><a href="#products" title="products">products</a></li>
<?php if ($currentUser !== null): ?>
          <li><a href="#cloud" title="プシューメイキィに保存したメモ">保存済み</a></li>
          <li>
            <form method="post" action="./" class="nav_form">
              <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
              <input type="hidden" name="account_action" value="logout" />
              <button type="submit" class="link_button" title="ログアウト"><?php echo pmemoH($currentUser["name"]); ?>さん / ログアウト</button>
            </form>
          </li>
<?php else: ?>
          <li><a href="#login" title="プシューメイキィでログイン">ログイン</a></li>
<?php endif; ?>
        </ul>
      </nav>
    </header>

    <div class="modal" id="products">
      <div>
        <a href="#mc"></a>
        <div>
          <span>プロダクト</span>
          <div>
            <div class="yokoori center" id="app">
              <?php echo($setProductData); ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="login">
      <div>
        <a href="#mc"></a>
        <div>
          <span>プシューメイキィでログイン</span>
          <div>
<?php if ($pmemoSignInError !== ""): ?>
            <p class="note">ログインできませんでした：<?php echo pmemoH($pmemoSignInError); ?></p>
<?php endif; ?>
<?php if ($currentUser !== null): ?>
            <p><?php echo pmemoH($currentUser["name"]); ?>さん（<?php echo pmemoH($currentUser["username"]); ?>）としてログイン中です。</p>
<?php elseif (PmemoAccount::ready()): ?>
            <p>
              プシューメイキィでログインしますか？
              ログインすることにより、メモをサーバに保存して別の端末から続きを書けるようになります。
            </p>
            <a href="<?php echo pmemoH(PmemoAccount::loginUrl()); ?>" class="button">ログイン</a>
            <p class="note">
              プシューメイキィをお持ちでない場合は
              <a href="<?php echo pmemoH(PmemoAccount::createUrl()); ?>">こちらで作成</a>
              してください。作成したその足でこちらにログインできます。
            </p>
<?php else: ?>
            <?php /* 押しても行き止まりになるリンクは出さず、理由だけを書きます。
                     ここはPHPが書き出すHTMLなので、JavaScriptが無くても読めます。 */ ?>
            <p>いまプシューメイキィと連携できないため、ログインできません。</p>
            <p class="note"><?php echo pmemoH(PmemoAccount::unavailableReason()); ?></p>
            <p class="note">メモの作成・編集・ダウンロードは、これまでどおりご利用いただけます（サーバへの保存と端末間の同期だけが使えません）。</p>
<?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <main>
<?php if ($flash !== ""): ?>
      <p class="flash" role="status"><?php echo pmemoH($flash); ?></p>
<?php endif; ?>

<?php if ($canUndoClear): ?>
      <form class="flash undo_form" method="post" action="./">
        <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
        <input type="hidden" name="action" value="undo_clear" />
        <span>削除する前の内容を取っておいてあります。</span>
        <button type="submit">元に戻す</button>
      </form>
<?php endif; ?>

<?php if ($lossyWarning): ?>
      <div class="warning" id="lossy">
        <h3>この文字コードでは保存できない文字があります</h3>
        <p>
          <strong><?php echo pmemoH($editor["charset"]); ?></strong> には次の文字が含まれておらず、このまま保存すると「?」に置き換わります。
        </p>
        <p class="lost_chars">
<?php foreach ($editor["lost"] as $lostChar): ?>
          <span><?php echo pmemoH($lostChar); ?></span>
<?php endforeach; ?>
        </p>
        <p>
          これらの文字を残したい場合は、文字コードを <strong>UTF-8</strong> に変えてから保存し直してください。
          （実際にその文字コードへ変換してみないと分からないため、この確認はサーバ側で行っています）
        </p>
        <form method="post" action="./">
          <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
          <input type="hidden" name="action" value="download" />
          <input type="hidden" name="force_lossy" value="1" />
          <!-- 改行を含む本文を input[hidden] のvalue属性に押し込むとブラウザによって
               改行の扱いが変わるため、隠したtextareaでそのまま持ち回します。 -->
          <textarea name="Memo" style="display:none"><?php echo pmemoH($editor["body"]); ?></textarea>
          <input type="hidden" name="fileName" value="<?php echo pmemoH($editor["fileName"]); ?>" />
          <input type="hidden" name="charset" value="<?php echo pmemoH($editor["charset"]); ?>" />
          <input type="hidden" name="eol" value="<?php echo pmemoH($editor["eol"]); ?>" />
          <div class="buttons">
            <button type="submit">それでもこのまま保存する</button>
            <a class="link_button" href="./#editor">やめて書き直す</a>
          </div>
        </form>
      </div>
<?php endif; ?>

      <section>
        <h3>プシューメモとテキストエディターの概要</h3>
        <div class="readmore">
          <input id="readmore" type="checkbox" />
          <label for="readmore" title="もっと見るのボタン"></label>
          <div>
            <p>このメモ帳には次のような特徴があり、ご利用いただけます、データの保護機能例えばブラウザが突然クラッシュした際や誤ってリロードを行ってしまった際にも書き込みのたびに一時保存を行うのでブラウザを開き直すと復元が行われます。文字コードの選択機能、UTF-8のほかShift_JIS、EUC-JP、ISO-2022-JP、ASCIIを選んで保存できます（変換はサーバ側で行うため、日本語や機種依存文字もきちんとその文字コードのファイルになります）。改行コードの選択機能、Windows向けのCRLF、Linux/macOS向けのLF、クラシックMac向けのCRを選べます。文字のカウント機能なん文字あるのかがリアルタイムでわかりますので、メモいがにもお持ちのテキストが何も自家知りたい際にもご利用いただけます。大文字、小文字一括切り替え機能がついておりますので、大文字でないと嫌だ、小文字でないと嫌だと言うこだわりも満たせます！。削除ボタン、この機能によりキーボードの削除ボタンで細々消す必要がなく一気に元の状態に戻せます。ファイルの読み込み機能特に読み込み制限は無いので、JPGやEXEといったファイルも読み込めますし以前保存したメモ帳やWindows標準のノートパットで書いたtxtのデータ等の編集が可能です（Shift_JISで書かれた古いtxtも文字コードを判別して読み込みます）。もちろんJSONやHTMLといったプログラム系ファイルも編集時可能です。文字置換機能により一部分だけ削除したり置き換えたりできます、この機能をあまり使った事がない方へ注意点（置換後の文字がない場合そこの文字は削除されます、大文字小文字は区別して置換を行います。）（サイズが自由な反面、サイズの大きいファイルは端末やブラウザアプリがクラッシュする可能性がございますので程々なサイズを選び、お気をつけてご利用ください。）。</p>
            <p>このメモ帳はJavaScriptが無くても動きます。入力、保存（ダウンロード）、読込、置換、大文字小文字の変換は、すべて普通のフォーム送信としてサーバ（PHP）が処理するので、JavaScriptを切っている環境でも、古くてBlobやダウンロード属性に対応していないブラウザでも同じように使えます。JavaScriptが動く環境では、文字数のリアルタイム表示や書き込みのたびの一時保存、拡大縮小といった機能が追加で有効になります。</p>
            <p>プシューメイキィ（PusyuuIPSのメイキィ）でログインすると、メモをサーバに保存して別の端末から続きを書けます。保存されたメモはメイキィの情報と同じAES-256-GCMで暗号化してからディスクに書き込まれ、ログインしているあなた以外には読めません。ログインせずに使う場合、メモが端末の外に出ることは一切ありません。</p>
          </div>
        </div>
      </section>

      <section id="editor">
        <h3>エディタ</h3>
        <form class="form" name="MemoForm" id="memoForm" method="post" action="./" accept-charset="UTF-8">
          <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
          <input type="hidden" name="memo_id" id="memoId" value="<?php echo pmemoH($editor["memo_id"]); ?>" />
          <input type="hidden" name="memo_loaded_title" id="memoLoadedTitle" value="<?php echo pmemoH($editor["memo_loaded_title"]); ?>" />

          <textarea name="Memo" id="memoText" placeholder="メモを入力" style="font-size: <?php echo (int)pmemoFontSize(); ?>px;"><?php echo pmemoH($editor["body"]); ?></textarea>
          <span id="moziCount">Counter</span>

          <div class="editor_settings">
            <label for="charsetSelect">文字コード</label>
            <select id="charsetSelect" name="charset">
<?php foreach (pmemoCharsets() as $key => $charset): ?>
              <option value="<?php echo pmemoH($key); ?>"<?php echo $editor["charset"] === $key ? ' selected' : ''; ?>><?php echo pmemoH($charset["label"]); ?></option>
<?php endforeach; ?>
            </select>

            <label for="eolSelect">改行コード</label>
            <select id="eolSelect" name="eol">
<?php foreach (pmemoEols() as $key => $eol): ?>
              <option value="<?php echo pmemoH($key); ?>"<?php echo $editor["eol"] === $key ? ' selected' : ''; ?>><?php echo pmemoH($eol["label"]); ?></option>
<?php endforeach; ?>
            </select>

            <label for="fileName">ファイル名</label>
            <input id="fileName" name="fileName" value="<?php echo pmemoH($editor["fileName"]); ?>" placeholder="空の場合はデフォルトのファイル名が適応されます。" />
          </div>

          <!--
            文字置換の入力欄です。JavaScriptが動く環境ではモーダルとして開きますが、
            動かない環境ではそのまま並んで見えるようにしています。
          -->
          <div class="myModal" id="replaceModal">
            <div>
              <p>文字の置換</p>
              <div>
                <input id="replaceAllowWord" name="replaceAllowWord" placeholder="置換対象文字" />
                <input id="replaceWord" name="replaceWord" placeholder="置換後の文字" />
              </div>
              <div class="buttons">
                <button type="submit" name="action" value="replace">実行</button>
                <button type="button" id="closeReplaceModal" class="js_only" onclick="closeReplaceSettingModal()">閉じる</button>
              </div>
            </div>
          </div>

          <div class="buttons">
            <button type="submit" name="action" value="download">保存（ダウンロード）</button>
<?php if ($currentUser !== null): ?>
            <button type="submit" name="action" value="cloud_save">プシューメイキィに保存</button>
<?php endif; ?>
            <button type="submit" name="action" value="clear">削除</button>
            <button type="submit" name="action" value="upper">すべてを大文字</button>
            <button type="submit" name="action" value="lower">すべてを小文字</button>
            <!-- 置換の入力欄はJSが無い環境では下に開いたまま置いてあるので、
                 このボタン（モーダルを開くだけのもの）はJSがあるときにだけ出します。 -->
            <button type="button" class="js_only" id="replaceSubmit" onclick="showReplaceSettingModal()">文字置換</button>
            <button type="button" class="js_only" onclick="allcopy()">すべてコピー</button>
            <button type="submit" name="action" value="zoom_in">拡大</button>
            <button type="submit" name="action" value="zoom_out">縮小</button>
          </div>
        </form>

        <form class="upload_form" method="post" action="./" enctype="multipart/form-data">
          <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
          <input type="hidden" name="action" value="upload" />
          <label for="memoFile">ファイルを読み込む</label>
          <input id="memoFile" name="memoFile" type="file" />
          <button type="submit">読込</button>
          <span class="note">Shift_JISやEUC-JPで書かれた古いtxtも、サーバ側で文字コードを判別して読み込みます。</span>
        </form>
      </section>

<?php if ($currentUser !== null): ?>
      <section id="cloud">
        <h3>プシューメイキィに保存したメモ</h3>
<?php if ($cloudError !== ""): ?>
        <p class="flash"><?php echo pmemoH($cloudError); ?></p>
<?php elseif (empty($cloudMemos)): ?>
        <p>まだ保存されたメモはありません。エディタで「プシューメイキィに保存」を押すとここに並びます。</p>
<?php else: ?>
        <ul class="cloud_list">
<?php foreach ($cloudMemos as $memo): ?>
          <li>
            <span class="cloud_title"><?php echo pmemoH($memo["title"]); ?></span>
            <span class="cloud_meta">
              <?php echo pmemoH(number_format((int)$memo["chars"])); ?>文字 /
              <?php echo pmemoH($memo["charset"]); ?> /
              <?php echo pmemoH(date("Y-m-d H:i", (int)$memo["updated_at"])); ?>
            </span>
            <span class="cloud_buttons">
              <form method="post" action="./">
                <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
                <input type="hidden" name="account_action" value="cloud_load" />
                <input type="hidden" name="memo_id" value="<?php echo pmemoH($memo["id"]); ?>" />
                <button type="submit">開く</button>
              </form>
              <form method="post" action="./" class="delete_form">
                <input type="hidden" name="pmemo_token" value="<?php echo pmemoH($csrfToken); ?>" />
                <input type="hidden" name="account_action" value="cloud_delete" />
                <input type="hidden" name="memo_id" value="<?php echo pmemoH($memo["id"]); ?>" />
                <button type="submit">削除</button>
              </form>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>
<?php endif; ?>
    </main>

    <footer>
      <div><?php echo function_exists('notationDisp') ? notationDisp("notation") : ''; ?></div>
      <div><?php echo function_exists('notationDisp') ? notationDisp("copyright") : ''; ?></div>
    </footer>

    <!--
      スクリプトはページの一番下で、しかも defer 相当のタイミングで読み込みます。
      古いブラウザでこれらの取得に失敗しても、エディタ本体はすでにHTMLとして
      出来上がっているため何も壊れません。
    -->
    <script src="<?php echo pmemoH(pmemoAssetUrl('./assets/scripts/memo.js')); ?>"></script>
    <script src="<?= pmemoSiteBase('main') ?>/pusyuusystem/scripts/dragg-and-drop.js"></script>
    <script src="<?= pmemoSiteBase('main') ?>/pusyuusystem/scripts/touch-device-tool-tip.js"></script>
    <!-- privacy_notice.js の script タグより前に置く -->
    <script>
      window.PusyuuPrivacyNoticeConfig = {
        // ---- 機能1(初回アクセス時の自動お知らせ)のカスタマイズ ----
        title: "プライバシーに関するお知らせ",
        // bodyHtml: "<p>...</p>", // 本文を丸ごと差し替えたい場合のみ指定(指定すると下のserviceNameは使われなくなる)
        serviceName: "プシューサービス", // 省略時は特定サービス名を出さず一般論の文章になる
        fundingNote:
          '広告についての方針は<a href="<?= pmemoSiteBase('main') ?>/rules" ' +
          'target="_blank">ルールズ</a>をご覧ください。',
        closeButtonText: "同意する",
        hostsIntroText: "このページ読み込み時に検出した外部の通信先：",
        hostsNoneText: "このページ読み込み時には、外部への通信は検出されませんでした。",
        checkDelay: 1500,   // load後、検出確定までの待ち時間(ms)。既定1500
        maxHostDisplay: 10, // 検出したホスト名を一覧表示する最大件数。既定10
        storageKey: "pusyuu_privacy_notice_dismissed", // 表示済みフラグの保存キー名

        // ---- "js-dependency"プリセット(機能2)のカスタマイズ ----
        jsDependency: {
          level: "low", // このページの実情に合わせて "none" | "low" | "medium" | "high"
          brokenFeatures: [
            "ハンバーガーメニューの閉じる機能",
            "ページ読み込み時の演出の閉じる機能",
            "ページ内の一部アニメーション"
          ],
          fallbackNote:
            '詳しい方針は<a href="<?= pmemoSiteBase('main') ?>/rules/javascript/" ' +
            'target="_blank">JavaScriptの取り扱いに関する方針</a>をご覧ください。'
        },

        triggerCloseButtonText: "閉じる" // 機能2のモーダルの閉じるボタン文言
      };
    </script>
    <script src="<?= pmemoSiteBase('main') ?>/pusyuusystem/scripts/privacy_notice.js"></script>
  </body>
</html>
