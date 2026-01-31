# yoSSO

**yoSSO** は軽量なシングルサインオン（SSO）認証システムです。PHPベースのWebアプリケーション間で統一された認証機能を提供します。

---

## 📋 目次

1. [概要](#概要)
2. [機能](#機能)
3. [ディレクトリ構成](#ディレクトリ構成)
4. [セットアップ](#セットアップ)
5. [使用方法](#使用方法)
6. [クライアントアプリ連携](#クライアントアプリ連携)
7. [API リファレンス](#api-リファレンス)
8. [設定](#設定)
9. [トラブルシューティング](#トラブルシューティング)

---

## 概要

yoSSOは、複数のWebアプリケーション間で共通の認証を実現する軽量なSSOサーバーです。認証コードフローを使用しており、ユーザーが一度ログインすれば、連携している他のアプリケーションへのアクセスが可能になります。

### 認証フロー

```
[クライアントアプリ] → [yoSSO] → ログイン → [認証コード発行] → [クライアントアプリにリダイレクト] → [コード検証] → [認証完了]
```

---

## 機能

- 🔐 **ユーザー認証**: ユーザー名/パスワードによるログイン
- 🔄 **認証コードフロー**: 一時コードによるセキュアな認証
- 👤 **ユーザー管理**: コマンドラインからのユーザー追加
- 🔒 **パスワード変更**: ログインユーザー自身によるパスワード変更
- ⏰ **コード有効期限**: 認証コードは5分間有効、1回のみ使用可能

---

## ディレクトリ構成

```
yoSSO/
├── index.php           # メインログインページ
├── validate.php        # 認証コード検証API
├── change_password.php # パスワード変更ページ
├── add_user.php        # ユーザー追加スクリプト（CLI）
├── setup.php           # 初期セットアップスクリプト
├── templates/
│   └── session_config.php.template  # セッション設定テンプレート
├── assets/
│   └── css/
│       └── style.css   # スタイルシート
├── data/
│   ├── users.json      # ユーザーデータ
│   ├── codes.json      # 認証コード（一時ファイル）
│   └── config.json     # システム設定（オプション）
└── README.md
```

---

## セットアップ

### 1. 必要環境

- PHP 7.4 以上
- Webサーバー（Apache, nginx, PHP Built-in Server など）

### 2. 初期セットアップ

まず、`setup.php` を実行してデータディレクトリと初期ファイルを作成します：

```bash
cd yoSSO
php setup.php
```

実行すると以下が作成されます：
- `data/` ディレクトリ
- `data/users.json` - ユーザーデータ（デフォルト管理者含む）
- `data/codes.json` - 認証コード
- `data/config.json` - システム設定
- `../shared/` ディレクトリ（存在しない場合）
- `../shared/session_config.php` - セッション設定

出力例：
```
=================================
  yoSSO Setup
=================================

[Data Directory]
[✓] Created data directory
[✓] Created users.json with default admin user
    Username: admin
    Password: admin
[✓] Created codes.json
[✓] Created config.json with default settings

[Shared Directory]
[·] Shared directory already exists

[Session Configuration]
[✓] Created session_config.php

=================================
  Setup Complete!
=================================
```

### 3. 初期管理者アカウント

セットアップ時に作成されるデフォルトの管理者：

| 項目 | 値 |
|------|-----|
| ユーザー名 | `admin` |
| パスワード | `admin` |

> ⚠️ **重要**: 本番環境では必ずパスワードを変更してください。

---

## 使用方法

### ログイン

1. ブラウザで `http://localhost:8001` にアクセス
2. ユーザー名とパスワードを入力
3. 「Sign In」ボタンをクリック

### ログアウト

ログイン後の画面から「Sign Out」ボタンをクリック、または以下のURLにアクセス：

```
http://localhost:8001/index.php?logout=1
```

### パスワード変更

1. ログイン状態で `http://localhost:8001/change_password.php` にアクセス
2. 現在のパスワード、新しいパスワード、確認用パスワードを入力
3. 「Update Password」ボタンをクリック

### ユーザー追加（CLI）

コマンドラインから新規ユーザーを追加できます：

```bash
cd yoSSO
php add_user.php
```

対話式で以下の情報を入力：
- Username: ユーザー名
- Password: パスワード（入力時は非表示）
- Display Name: 表示名（省略可能）

---

## クライアントアプリ連携

### SSO認証フロー

クライアントアプリがyoSSOと連携する方法を説明します。

#### Step 1: 認証リクエスト

ユーザーをyoSSOにリダイレクトします：

```php
$yosso_url = "http://localhost:8001/index.php";
$redirect_uri = urlencode("http://localhost:8080/callback.php");
$app_name = urlencode("My App");

header("Location: {$yosso_url}?redirect_uri={$redirect_uri}&app_name={$app_name}");
```

| パラメータ | 説明 |
|----------|------|
| `redirect_uri` | 認証完了後のリダイレクト先URL |
| `app_name` | ログイン画面に表示するアプリ名（オプション） |

#### Step 2: コールバック処理

yoSSOは認証成功後、`redirect_uri` にコード付きでリダイレクトします：

```
http://localhost:8080/callback.php?code=abc123...
```

#### Step 3: コード検証

受け取ったコードをyoSSOで検証します：

```php
<?php
// callback.php
$code = $_GET['code'] ?? '';

if ($code) {
    $response = file_get_contents(
        "http://localhost:8001/validate.php?code=" . urlencode($code)
    );
    $result = json_decode($response, true);
    
    if ($result['valid']) {
        // 認証成功
        $_SESSION['user'] = $result['username'];
        header("Location: /dashboard.php");
    } else {
        // 認証失敗
        echo "Authentication failed: " . $result['error'];
    }
}
```

### セッション確認

ユーザーがログイン済みかどうかを確認：

```php
<?php
require_once __DIR__ . '/../shared/session_config.php';
session_start();

if (isset($_SESSION['yosso_user'])) {
    echo "Logged in as: " . $_SESSION['yosso_user'];
} else {
    echo "Not logged in";
}
```

---

## API リファレンス

### POST /index.php - ログイン

ユーザー認証を行います。

**リクエスト（フォーム）**

| パラメータ | 型 | 説明 |
|----------|-----|------|
| username | string | ユーザー名 |
| password | string | パスワード |

**クエリパラメータ**

| パラメータ | 型 | 説明 |
|----------|-----|------|
| redirect_uri | string | 認証後のリダイレクト先 |
| app_name | string | 表示用アプリ名 |

---

### GET/POST /validate.php - コード検証

認証コードを検証し、ユーザー情報を返します。

**リクエスト**

| パラメータ | 型 | 説明 |
|----------|-----|------|
| code | string | 認証コード |

**レスポンス（成功時）**

```json
{
    "valid": true,
    "username": "admin"
}
```

**レスポンス（失敗時）**

```json
{
    "valid": false,
    "error": "Invalid or expired code"
}
```

> ⚠️ 認証コードは一度使用すると無効になります（ワンタイム使用）

---

## 設定

### config.json（オプション）

`data/config.json` を作成することで、システムをカスタマイズできます：

```json
{
    "system_name": "MySSO Portal",
    "target_env": "production",
    "base_color": "#3b82f6"
}
```

| 項目 | 説明 | デフォルト値 |
|------|------|-------------|
| system_name | ログイン画面に表示されるシステム名 | yoSSO |
| target_env | 環境識別子（dev/production） | dev |
| base_color | テーマカラー | null |

### セッション設定

セッション設定は `setup.php` 実行時に `shared/session_config.php` へ展開されます。

#### テンプレートの場所

```
yoSSO/templates/session_config.php.template  # テンプレート（yoSSO管理）
    ↓ setup.php 実行時にコピー
shared/session_config.php                     # 実際の設定ファイル
```

#### 既存設定とのマージ

`setup.php` は既存の `session_config.php` がある場合、以下の動作をします：

1. **設定値の保持**: 既存のセッション有効期限やセッション名などの設定を保持
2. **バックアップ作成**: 変更前に `.backup.YYYYMMDD_HHMMSS` ファイルを作成
3. **競合検出**: 互換性のない設定がある場合はエラーとして表示

競合の例：
```
⚠ CONFLICTS DETECTED in session_config.php:

  [CONFLICT] Cookie path is '/app' instead of '/'. This may prevent session sharing between apps.
    Current: /app
    Expected: /
```

#### セッション有効期限の値

| lifetime値 | 動作 |
|-----------|------|
| `0`（デフォルト） | ブラウザを閉じるとセッション終了 |
| `3600` | 1時間 |
| `86400` | 24時間 |
| `604800` | 1週間 |

#### 有効期限を変更する場合

`shared/session_config.php` の `$sessionLifetime` を修正します：

```php
// Session lifetime in seconds
$sessionLifetime = 86400;  // 24時間
```

> 💡 カスタマイズ後も `setup.php` を再実行すると設定は保持されます。

### 認証コードの有効期限

認証コード（SSO連携時に発行される一時コード）の有効期限は `index.php` で設定されています：

```php
$codes[$code] = [
    'username' => $username,
    'expires_at' => time() + 300  // 5分間有効
];
```

| 項目 | 有効期限 | 管理ファイル |
|------|---------|-------------|
| セッション | ブラウザ閉じるまで（デフォルト） | `shared/session_config.php` |
| 認証コード | 5分間（1回使用で無効化） | `yoSSO/index.php` |

---

## トラブルシューティング

### 「Invalid credentials」エラー

- ユーザー名・パスワードが正しいか確認
- `data/users.json` が存在するか確認

### 認証コードが無効になる

認証コードは以下の条件で無効になります：
- 発行から5分経過
- 1回使用された後

### セッションが維持されない

- `shared/session_config.php` が正しく読み込まれているか確認
- 複数アプリ間でセッション設定が一致しているか確認

### ユーザーが追加できない

```bash
# dataディレクトリに書き込み権限があるか確認
chmod 755 data/
chmod 644 data/users.json
```

---
