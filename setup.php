<?php
/**
 * yoSSO セットアップスクリプト
 * 
 * データディレクトリと初期ファイルを作成します。
 * また、shared/ ディレクトリに共有設定ファイルを展開します。
 * CLIまたはWebブラウザから実行可能です。
 * 
 * 使用方法 (CLI): php setup.php
 * 使用方法 (Web): ブラウザで setup.php にアクセス
 */

$data_dir = __DIR__ . '/data';
$users_file = $data_dir . '/users.json';
$codes_file = $data_dir . '/codes.json';
$config_file = $data_dir . '/config.json';

// 共有ディレクトリのパス
$shared_dir = dirname(__DIR__) . '/shared';
$session_config_template = __DIR__ . '/templates/session_config.php.template';
$session_config_target = $shared_dir . '/session_config.php';

$is_cli = php_sapi_name() === 'cli';
$has_errors = false;
$has_warnings = false;

/**
 * 出力用ヘルパー関数
 * CLIとWebで出力形式を切り替える
 */
function output($message, $is_cli, $type = 'info') {
    $prefix = '';
    $suffix = '';
    
    if (!$is_cli) {
        switch ($type) {
            case 'success':
                $prefix = '<span style="color: #22c55e;">';
                $suffix = '</span>';
                break;
            case 'error':
                $prefix = '<span style="color: #ef4444; font-weight: bold;">';
                $suffix = '</span>';
                break;
            case 'warning':
                $prefix = '<span style="color: #f59e0b;">';
                $suffix = '</span>';
                break;
            case 'conflict':
                $prefix = '<span style="color: #ef4444; background: rgba(239,68,68,0.1); padding: 2px 6px; border-radius: 3px;">';
                $suffix = '</span>';
                break;
        }
    }
    
    if ($is_cli) {
        echo $message . "\n";
    } else {
        echo $prefix . htmlspecialchars($message) . $suffix . "<br>";
    }
}

/**
 * 2つの session_config.php ファイルを比較し、競合を検出する
 * 
 * @param string $existing_content 既存ファイルの内容
 * @param string $template_content テンプレートファイルの内容
 * @return array ['compatible' => bool, 'conflicts' => array, 'can_merge' => bool]
 */
function analyze_session_config($existing_content, $template_content) {
    $result = [
        'compatible' => true,
        'conflicts' => [],
        'can_merge' => true,
        'existing_values' => [],
        'template_values' => []
    ];
    
    // 既存ファイルから設定値を抽出
    // sessionLifetime または lifetime パラメータを探す
    if (preg_match('/\$sessionLifetime\s*=\s*(\d+)/', $existing_content, $m)) {
        $result['existing_values']['sessionLifetime'] = (int)$m[1];
    } elseif (preg_match('/session_set_cookie_params\s*\(\s*(\d+)/', $existing_content, $m)) {
        $result['existing_values']['sessionLifetime'] = (int)$m[1];
    } elseif (preg_match('/\$cookieParams\[.lifetime.\]/', $existing_content)) {
        $result['existing_values']['sessionLifetime'] = 0; // デフォルト値
    }
    
    // session_name を確認
    if (preg_match('/session_name\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $existing_content, $m)) {
        if (strpos($existing_content, '// session_name') === false) {
            $result['existing_values']['session_name'] = $m[1];
        }
    }
    
    // Cookie パスを確認
    if (preg_match('/session_set_cookie_params\s*\([^,]*,\s*[\'"]([^\'"]+)[\'"]/', $existing_content, $m)) {
        $result['existing_values']['cookie_path'] = $m[1];
    }
    
    // テンプレートから設定値を抽出
    if (preg_match('/\$sessionLifetime\s*=\s*(\d+)/', $template_content, $m)) {
        $result['template_values']['sessionLifetime'] = (int)$m[1];
    }
    
    // 競合を検出
    // Cookie パスの競合（ルートパス以外の場合、問題が発生する可能性）
    if (isset($result['existing_values']['cookie_path']) && 
        $result['existing_values']['cookie_path'] !== '/') {
        $result['conflicts'][] = [
            'type' => 'cookie_path',
            'existing' => $result['existing_values']['cookie_path'],
            'expected' => '/',
            'message' => "Cookie パスが '/' ではなく '{$result['existing_values']['cookie_path']}' です。アプリ間でセッション共有ができない可能性があります。"
        ];
        $result['compatible'] = false;
    }
    
    // 根本的に異なる構造かどうかを確認
    if (strpos($existing_content, 'session_set_cookie_params') === false) {
        $result['conflicts'][] = [
            'type' => 'structure',
            'message' => "既存ファイルに session_set_cookie_params が含まれていません。互換性がない可能性があります。"
        ];
        $result['can_merge'] = false;
        $result['compatible'] = false;
    }
    
    return $result;
}

/**
 * テンプレートを既存の設定にマージし、カスタム値を保持する
 * 
 * @param string $existing_content 既存ファイルの内容
 * @param string $template_content テンプレートファイルの内容
 * @param array $analysis 分析結果
 * @return string マージされた設定内容
 */
function create_merged_config($existing_content, $template_content, $analysis) {
    // テンプレートをベースにする
    $merged = $template_content;
    
    // 既存の sessionLifetime がデフォルト以外の場合は保持
    if (isset($analysis['existing_values']['sessionLifetime']) && 
        $analysis['existing_values']['sessionLifetime'] !== 0) {
        $lifetime = $analysis['existing_values']['sessionLifetime'];
        $merged = preg_replace(
            '/\$sessionLifetime\s*=\s*\d+/',
            '$sessionLifetime = ' . $lifetime,
            $merged
        );
    }
    
    // session_name が有効になっていた場合は保持
    if (isset($analysis['existing_values']['session_name'])) {
        $session_name = $analysis['existing_values']['session_name'];
        $merged = preg_replace(
            '/\/\/\s*session_name\([\'"][^\'"]+[\'"]\)/',
            "session_name('" . $session_name . "')",
            $merged
        );
    }
    
    return $merged;
}

// 出力開始
if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>yoSSO セットアップ</title>";
    echo "<style>body{font-family:monospace;padding:2rem;background:#1a1a2e;color:#eee;line-height:1.6;}</style>";
    echo "</head><body><h1>yoSSO セットアップ</h1><pre>";
}

output("=================================", $is_cli);
output("  yoSSO セットアップ", $is_cli);
output("=================================", $is_cli);
output("", $is_cli);

// 1. データディレクトリを作成
output("[データディレクトリ]", $is_cli);
if (!file_exists($data_dir)) {
    if (mkdir($data_dir, 0755, true)) {
        output("[✓] data ディレクトリを作成しました", $is_cli, 'success');
    } else {
        output("[✗] data ディレクトリの作成に失敗しました", $is_cli, 'error');
        $has_errors = true;
    }
} else {
    output("[·] data ディレクトリは既に存在します", $is_cli);
}

// 2. デフォルト管理者付きの users.json を作成
if (!file_exists($users_file)) {
    $default_users = [
        'admin' => [
            'password' => password_hash('admin', PASSWORD_DEFAULT),
            'name' => 'Admin User'
        ]
    ];
    if (file_put_contents($users_file, json_encode($default_users, JSON_PRETTY_PRINT))) {
        output("[✓] users.json を作成しました（デフォルト管理者付き）", $is_cli, 'success');
        output("    ユーザー名: admin", $is_cli);
        output("    パスワード: admin", $is_cli);
    } else {
        output("[✗] users.json の作成に失敗しました", $is_cli, 'error');
        $has_errors = true;
    }
} else {
    output("[·] users.json は既に存在します", $is_cli);
}

// 3. codes.json を作成
if (!file_exists($codes_file)) {
    if (file_put_contents($codes_file, '{}')) {
        output("[✓] codes.json を作成しました", $is_cli, 'success');
    } else {
        output("[✗] codes.json の作成に失敗しました", $is_cli, 'error');
        $has_errors = true;
    }
} else {
    output("[·] codes.json は既に存在します", $is_cli);
}

// 4. config.json を作成
if (!file_exists($config_file)) {
    $default_config = [
        'system_name' => 'yoSSO',
        'target_env' => 'dev'
    ];
    if (file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT))) {
        output("[✓] config.json を作成しました（デフォルト設定）", $is_cli, 'success');
    } else {
        output("[✗] config.json の作成に失敗しました", $is_cli, 'error');
        $has_errors = true;
    }
} else {
    output("[·] config.json は既に存在します", $is_cli);
}

output("", $is_cli);
output("[共有ディレクトリ]", $is_cli);

// 5. shared ディレクトリを作成（存在しない場合）
if (!file_exists($shared_dir)) {
    if (mkdir($shared_dir, 0755, true)) {
        output("[✓] shared ディレクトリを作成しました", $is_cli, 'success');
    } else {
        output("[✗] shared ディレクトリの作成に失敗しました", $is_cli, 'error');
        $has_errors = true;
    }
} else {
    output("[·] shared ディレクトリは既に存在します", $is_cli);
}

// 6. session_config.php を展開
output("", $is_cli);
output("[セッション設定]", $is_cli);

if (!file_exists($session_config_template)) {
    output("[✗] テンプレートファイルが見つかりません: templates/session_config.php.template", $is_cli, 'error');
    $has_errors = true;
} else {
    $template_content = file_get_contents($session_config_template);
    
    if (file_exists($session_config_target)) {
        // 既存ファイルがある場合 - 分析してマージ
        $existing_content = file_get_contents($session_config_target);
        $analysis = analyze_session_config($existing_content, $template_content);
        
        if (!empty($analysis['conflicts'])) {
            output("", $is_cli);
            output("⚠ session_config.php に競合が検出されました:", $is_cli, 'warning');
            output("", $is_cli);
            
            foreach ($analysis['conflicts'] as $conflict) {
                output("  [競合] " . $conflict['message'], $is_cli, 'conflict');
                if (isset($conflict['existing'])) {
                    output("    現在の値: " . $conflict['existing'], $is_cli, 'error');
                }
                if (isset($conflict['expected'])) {
                    output("    期待される値: " . $conflict['expected'], $is_cli);
                }
            }
            output("", $is_cli);
            $has_warnings = true;
        }
        
        if ($analysis['can_merge']) {
            // マージされた設定を作成
            $merged_content = create_merged_config($existing_content, $template_content, $analysis);
            
            // 内容が実際に変更されたかを確認
            if (trim($existing_content) === trim($merged_content)) {
                output("[·] session_config.php は最新です", $is_cli);
            } else {
                // 既存ファイルをバックアップ
                $backup_file = $session_config_target . '.backup.' . date('Ymd_His');
                copy($session_config_target, $backup_file);
                output("[·] 既存の設定をバックアップしました: " . basename($backup_file), $is_cli);
                
                // マージされた設定を書き込み
                if (file_put_contents($session_config_target, $merged_content)) {
                    output("[✓] session_config.php を更新しました（既存設定とマージ）", $is_cli, 'success');
                    
                    // 保持された値を表示
                    if (!empty($analysis['existing_values'])) {
                        output("    保持された設定:", $is_cli);
                        foreach ($analysis['existing_values'] as $key => $value) {
                            output("      - $key: $value", $is_cli);
                        }
                    }
                } else {
                    output("[✗] session_config.php の更新に失敗しました", $is_cli, 'error');
                    $has_errors = true;
                }
            }
        } else {
            output("[!] 自動マージできません。手動での確認が必要です。", $is_cli, 'warning');
            output("    既存ファイル: " . $session_config_target, $is_cli);
            output("    テンプレート: " . $session_config_template, $is_cli);
            $has_warnings = true;
        }
    } else {
        // 既存ファイルがない場合 - 新規作成
        if (file_put_contents($session_config_target, $template_content)) {
            output("[✓] session_config.php を作成しました", $is_cli, 'success');
        } else {
            output("[✗] session_config.php の作成に失敗しました", $is_cli, 'error');
            $has_errors = true;
        }
    }
}

// 結果サマリー
output("", $is_cli);
output("=================================", $is_cli);

if ($has_errors) {
    output("  セットアップがエラーで完了しました", $is_cli, 'error');
} elseif ($has_warnings) {
    output("  セットアップが警告付きで完了しました", $is_cli, 'warning');
} else {
    output("  セットアップ完了！", $is_cli, 'success');
}

output("=================================", $is_cli);
output("", $is_cli);

if (!$has_errors) {
    output("次のステップ:", $is_cli);
    output("  1. admin / admin でログイン", $is_cli);
    output("  2. パスワードを変更してください！", $is_cli);
}

if ($has_warnings) {
    output("", $is_cli);
    output("上記の警告を確認してください。", $is_cli, 'warning');
}

if (!$is_cli) {
    echo "</pre></body></html>";
}
