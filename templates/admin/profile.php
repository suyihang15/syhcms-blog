<?php
$roleLabels = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'subscriber' => '订阅者'];
$role = (string) $user['role'];
?>
<div class="admin-page-head">
    <div>
        <h2>个人资料</h2>
        <p>修改显示名称、邮箱与登录密码。</p>
    </div>
</div>

<div class="admin-layout-2">
    <div class="admin-panel">
        <div class="admin-panel-head"><h2>基本资料</h2></div>
        <form class="admin-form" action="<?= e(admin_url('profile/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <div class="admin-form-row">
                <label for="display_name">显示名称</label>
                <input class="admin-input" type="text" id="display_name" name="display_name" value="<?= e($user['display_name']) ?>" required>
                <span class="hint">显示在后台与文章作者处。</span>
            </div>
            <div class="admin-form-row">
                <label for="email">邮箱</label>
                <input class="admin-input" type="email" id="email" name="email" value="<?= e($user['email']) ?>" autocomplete="email">
            </div>

            <div class="admin-section-title">修改密码</div>
            <div class="admin-form-row">
                <label for="current_password">当前密码</label>
                <input class="admin-input" type="password" id="current_password" name="current_password" autocomplete="current-password">
                <span class="hint">仅修改密码时需要填写。</span>
            </div>
            <div class="admin-form-grid">
                <div class="admin-form-row">
                    <label for="new_password">新密码</label>
                    <input class="admin-input" type="password" id="new_password" name="new_password" autocomplete="new-password">
                    <span class="hint">至少 8 位，留空则不修改。</span>
                </div>
                <div class="admin-form-row">
                    <label for="confirm_password">确认新密码</label>
                    <input class="admin-input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                </div>
            </div>

            <div><button class="admin-btn admin-btn-primary" type="submit">保存资料</button></div>
        </form>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h2>账户信息</h2></div>
        <table class="admin-table">
            <tbody>
            <tr><th>登录名</th><td><?= e($user['login']) ?></td></tr>
            <tr><th>角色</th><td><span class="admin-badge"><?= e($roleLabels[$role] ?? $role) ?></span></td></tr>
            <tr><th>注册时间</th><td><?= e((string) $user['registered_at'] !== '' ? format_date((string) $user['registered_at'], 'Y-m-d H:i') : '—') ?></td></tr>
            </tbody>
        </table>
        <p class="admin-small admin-muted" style="margin:14px 0 0;">登录名不可修改。修改密码需要先验证当前密码。</p>
    </div>
</div>
