<?php
$roleLabels = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'subscriber' => '订阅者'];
$selfId     = (int) (Auth::user()['id'] ?? 0);
$isEdit     = is_array($edit ?? null);
$formTitle  = $isEdit ? '编辑用户' : '新建用户';
?>
<div class="admin-page-head">
    <div>
        <h2>用户</h2>
        <p>管理登录账号、角色与密码。删除用户前请先转移其内容。</p>
    </div>
    <?php if ($isEdit): ?>
    <a class="admin-btn" href="<?= e(admin_url('users/')) ?>">返回列表</a>
    <?php endif; ?>
</div>

<div class="admin-layout-3">
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2><?= e($formTitle) ?></h2>
        </div>
        <form class="admin-form" action="<?= e(admin_url('users/save/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <label for="login">登录名</label>
                <?php if ($isEdit): ?>
                <input class="admin-input" type="text" id="login" value="<?= e($edit['login']) ?>" disabled>
                <span class="hint">登录名创建后不可修改。</span>
                <?php else: ?>
                <input class="admin-input" type="text" id="login" name="login" required pattern="[A-Za-z0-9._\-]{3,60}" autocomplete="off">
                <span class="hint">3-60 位字母、数字、点、下划线或短横线。</span>
                <?php endif; ?>
            </div>
            <div class="admin-form-row">
                <label for="display_name">显示名称</label>
                <input class="admin-input" type="text" id="display_name" name="display_name" value="<?= e($isEdit ? $edit['display_name'] : '') ?>" required>
            </div>
            <div class="admin-form-row">
                <label for="email">邮箱</label>
                <input class="admin-input" type="email" id="email" name="email" value="<?= e($isEdit ? $edit['email'] : '') ?>" autocomplete="off">
                <span class="hint">用于接收评论通知与找回信息。</span>
            </div>
            <div class="admin-form-row">
                <label for="role">角色</label>
                <select class="admin-select" id="role" name="role">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>"<?= $isEdit && $edit['role'] === $role ? ' selected' : '' ?>><?= e($roleLabels[$role] ?? $role) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isEdit && (int) $edit['id'] === $selfId): ?><span class="hint">不能降级当前登录的管理员账号。</span><?php endif; ?>
            </div>
            <div class="admin-form-row">
                <label for="password"><?= $isEdit ? '重置密码' : '初始密码' ?></label>
                <input class="admin-input" type="password" id="password" name="password" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?>>
                <span class="hint"><?= $isEdit ? '留空则不修改，至少 8 位。' : '至少 8 位，创建后请提醒用户修改。' ?></span>
            </div>

            <div>
                <button class="admin-btn admin-btn-primary" type="submit"><?= $isEdit ? '保存修改' : '创建用户' ?></button>
            </div>
        </form>
    </div>

    <div class="admin-panel admin-panel-flush">
        <div class="admin-panel-head">
            <div>
                <h2>账号列表</h2>
                <p>共 <?= count($rows) ?> 个账号。</p>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr><th>用户</th><th>角色</th><th>内容</th><th>注册时间</th><th>操作</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a class="row-title" href="<?= e(admin_url('users/edit/' . (int) $row['id'] . '/')) ?>"><?= e($row['display_name'] !== '' ? $row['display_name'] : $row['login']) ?></a>
                        <div class="admin-small admin-muted"><?= e($row['login']) ?><?= $row['email'] !== '' ? ' · ' . e($row['email']) : '' ?></div>
                    </td>
                    <td><span class="admin-badge<?= $row['role'] === 'admin' ? ' admin-badge-publish' : '' ?>"><?= e($roleLabels[$row['role']] ?? $row['role']) ?></span></td>
                    <td class="admin-small admin-muted"><?= (int) $row['post_count'] ?> 篇文章 · <?= (int) $row['comment_count'] ?> 条评论</td>
                    <td class="admin-small admin-muted"><?= $row['registered_at'] !== '' ? e(format_date((string) $row['registered_at'], 'Y-m-d')) : '—' ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('users/edit/' . (int) $row['id'] . '/')) ?>">编辑</a>
                            <?php if ((int) $row['id'] !== $selfId): ?>
                            <form action="<?= e(admin_url('users/delete/')) ?>" method="post" data-confirm="确定删除用户“<?= e($row['login']) ?>”吗？其文章将转交给当前账号。">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">删除</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
