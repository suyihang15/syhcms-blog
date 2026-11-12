<?php
$groups = [];
foreach ($files as $rel => $info) {
    $groups[$info['dir']][] = $rel;
}
ksort($groups);
$currentInfo = $files[$currentFile] ?? ['size' => $fileSize, 'mtime' => $fileMtime, 'dir' => ''];
$codeVer = is_file(SYH_ROOT . '/public/assets/admin-code.js') ? (string) filemtime(SYH_ROOT . '/public/assets/admin-code.js') : '1';
?>
<div class="admin-page-head">
    <div>
        <h2>源码编辑</h2>
        <p>直接修改站点源码。保存前会做 PHP 语法检查，并把旧版本备份到 <code>data/backup/code</code>。</p>
    </div>
    <div>
        <a class="admin-btn" href="<?= e(admin_url()) ?>">返回仪表盘</a>
    </div>
</div>

<div class="admin-code-layout">
    <aside class="admin-panel admin-panel-flush admin-code-files">
        <div class="admin-code-search">
            <input class="admin-input" type="search" id="code-search" placeholder="搜索文件…" autocomplete="off">
        </div>
        <div class="admin-code-list" id="code-list">
            <?php foreach ($groups as $dir => $items): ?>
            <div class="admin-code-group">
                <div class="admin-code-group-label"><?= e($dir) ?></div>
                <?php foreach ($items as $rel): ?>
                <a class="admin-code-file<?= $rel === $currentFile ? ' is-active' : '' ?>"
                   href="<?= e(admin_url('code/?f=' . rawurlencode($rel))) ?>"
                   data-path="<?= e($rel) ?>">
                    <span><?= e(basename($rel)) ?></span>
                    <small><?= e(CodeEditor::humanSize((int) $files[$rel]['size'])) ?></small>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div class="admin-empty" id="code-no-result" hidden>没有匹配的文件。</div>
        </div>
        <div class="admin-code-note">
            可编辑范围：<code>src/</code>、<code>templates/</code>、<code>public/assets/</code>、<code>config.php</code>、入口文件。
        </div>
    </aside>

    <section class="admin-panel admin-panel-flush admin-code-main">
        <div class="admin-code-head">
            <div>
                <strong><?= e($currentFile) ?></strong>
                <span>修改于 <?= e(date('Y-m-d H:i', $fileMtime)) ?> · <?= e(CodeEditor::humanSize((int) $currentInfo['size'])) ?> · <?= (int) $lineCount ?> 行</span>
            </div>
            <div class="admin-code-actions">
                <button class="admin-btn admin-btn-primary" type="submit" form="code-form">保存文件</button>
            </div>
        </div>

        <form id="code-form" action="<?= e(admin_url('code/save/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="file" value="<?= e($currentFile) ?>">
            <div class="admin-code-editor">
                <pre class="admin-code-gutter" id="code-gutter" aria-hidden="true"><?php
                    $gutter = '';
                    for ($i = 1; $i <= $lineCount; $i++) {
                        $gutter .= $i . "\n";
                    }
                    echo e(rtrim($gutter, "\n"));
                ?></pre>
                <textarea class="admin-code-area" id="code-area" name="content" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off"><?= e($content) ?></textarea>
            </div>
            <div class="admin-code-foot">
                <span>Tab 缩进 · Ctrl / ⌘ + S 保存</span>
                <span>保存后立即生效；PHP 文件语法错误会被拦截。</span>
            </div>
        </form>
    </section>
</div>

<script src="/assets/admin-code.js?v=<?= e($codeVer) ?>"></script>
