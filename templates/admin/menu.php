<?php
$kind = $edit ? (string) $edit['kind'] : 'custom';
$kindLabels = ['custom' => '自定义', 'page' => '页面', 'post' => '文章', 'category' => '分类'];
$objectId = $edit ? (int) $edit['object_id'] : 0;
$parentId = $edit ? (int) $edit['parent_id'] : 0;
$editId   = $edit ? (int) $edit['id'] : 0;
?>
<div class="admin-page-head">
    <div>
        <h2>导航菜单</h2>
        <p>管理站点主导航，共 <?= count($rows) ?> 项。排序值越小越靠前。</p>
    </div>
    <a class="admin-btn" href="<?= e(admin_url()) ?>">返回仪表盘</a>
</div>

<div class="admin-layout-3">
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2><?= $edit ? '编辑菜单项' : '添加菜单项' ?></h2>
            <?php if ($edit): ?><a class="admin-btn admin-btn-sm" href="<?= e(admin_url('menu/')) ?>">取消编辑</a><?php endif; ?>
        </div>
        <form class="admin-form" action="<?= e(admin_url('menu/save/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= $editId ?>">
            <div class="admin-form-row">
                <label for="menu-label">名称</label>
                <input class="admin-input" type="text" id="menu-label" name="label" value="<?= e($edit['label'] ?? '') ?>" placeholder="留空时使用所选对象标题">
            </div>
            <div class="admin-form-row">
                <label for="menu-kind">类型</label>
                <select class="admin-select" id="menu-kind" name="kind">
                    <?php foreach ($kindLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $kind === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-row" data-kind-group="page"<?= $kind !== 'page' ? ' style="display:none"' : '' ?>>
                <label for="menu-object-page">选择页面</label>
                <select class="admin-select" id="menu-object-page" name="object_id"<?= $kind !== 'page' ? ' disabled' : '' ?>>
                    <option value="0">请选择页面</option>
                    <?php foreach ($pages as $page): ?>
                    <option value="<?= (int) $page['id'] ?>"<?= $kind === 'page' && $objectId === (int) $page['id'] ? ' selected' : '' ?>><?= e($page['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-row" data-kind-group="post"<?= $kind !== 'post' ? ' style="display:none"' : '' ?>>
                <label for="menu-object-post">选择文章</label>
                <select class="admin-select" id="menu-object-post" name="object_id"<?= $kind !== 'post' ? ' disabled' : '' ?>>
                    <option value="0">请选择文章</option>
                    <?php foreach ($posts as $post): ?>
                    <option value="<?= (int) $post['id'] ?>"<?= $kind === 'post' && $objectId === (int) $post['id'] ? ' selected' : '' ?>><?= e($post['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-row" data-kind-group="category"<?= $kind !== 'category' ? ' style="display:none"' : '' ?>>
                <label for="menu-object-category">选择分类</label>
                <select class="admin-select" id="menu-object-category" name="object_id"<?= $kind !== 'category' ? ' disabled' : '' ?>>
                    <option value="0">请选择分类</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= $kind === 'category' && $objectId === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-row" data-kind-group="custom"<?= $kind !== 'custom' ? ' style="display:none"' : '' ?>>
                <label for="menu-url">链接地址</label>
                <input class="admin-input" type="text" id="menu-url" name="url" value="<?= e($edit['url'] ?? '') ?>" placeholder="/about/ 或 https://example.com/"<?= $kind !== 'custom' ? ' disabled' : '' ?>>
                <span class="hint">页面、文章、分类会自动生成链接，无需填写。</span>
            </div>

            <div class="admin-form-row">
                <label for="menu-parent">父级菜单</label>
                <select class="admin-select" id="menu-parent" name="parent_id">
                    <option value="0">无（顶级）</option>
                    <?php foreach ($rows as $row): ?>
                    <?php if ((int) $row['id'] === $editId) { continue; } ?>
                    <option value="<?= (int) $row['id'] ?>"<?= $parentId === (int) $row['id'] ? ' selected' : '' ?>><?= (int) $row['parent_id'] > 0 ? '— ' : '' ?><?= e($row['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-grid">
                <div class="admin-form-row">
                    <label for="menu-target">打开方式</label>
                    <select class="admin-select" id="menu-target" name="target">
                        <option value=""<?= ($edit['target'] ?? '') !== '_blank' ? ' selected' : '' ?>>当前窗口</option>
                        <option value="_blank"<?= ($edit['target'] ?? '') === '_blank' ? ' selected' : '' ?>>新窗口</option>
                    </select>
                </div>
                <div class="admin-form-row">
                    <label for="menu-sort">排序</label>
                    <input class="admin-input" type="number" id="menu-sort" name="sort" value="<?= (int) ($edit['sort'] ?? 0) ?>" step="1">
                </div>
            </div>

            <div><button class="admin-btn admin-btn-primary" type="submit"><?= $edit ? '保存修改' : '添加菜单项' ?></button></div>
        </form>
        <p class="admin-small admin-muted" style="margin:14px 0 0;">提示：只有顶级菜单会直接显示，子菜单在悬停或移动端展开。</p>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h2>菜单结构</h2></div>
        <?php if ($rows): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>名称</th>
                    <th>类型</th>
                    <th>链接</th>
                    <th>排序</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <span class="row-title"><?= (int) $row['parent_id'] > 0 ? '<span class="admin-menu-tree">↳</span> ' : '' ?><?= e($row['label']) ?></span>
                        <?php if ((string) $row['target'] === '_blank'): ?><span class="admin-small admin-muted">（新窗口）</span><?php endif; ?>
                    </td>
                    <td><span class="admin-badge"><?= e($kindLabels[(string) $row['kind']] ?? $row['kind']) ?></span></td>
                    <td class="admin-small"><code style="word-break:break-all;"><?= e($row['url']) ?></code></td>
                    <td><?= (int) $row['sort'] ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('menu/?edit=' . (int) $row['id'])) ?>">编辑</a>
                            <form action="<?= e(admin_url('menu/delete/')) ?>" method="post" data-confirm="确定删除菜单项“<?= e($row['label']) ?>”吗？其子项将变为顶级菜单。">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="admin-empty">还没有菜单项。</div>
        <?php endif; ?>
    </div>
</div>
