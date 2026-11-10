<?php
$isTag = $taxonomy === 'post_tag';
$editTaxonomy = $edit ? (string) $edit['taxonomy'] : $taxonomy;
$nameById = [];
foreach ($rows as $row) {
    $nameById[(int) $row['id']] = (string) $row['name'];
}
$catTotal = (int) DB::val("SELECT COUNT(*) FROM terms WHERE taxonomy = 'category'", [], 0);
$tagTotal = (int) DB::val("SELECT COUNT(*) FROM terms WHERE taxonomy = 'post_tag'", [], 0);
$slugValue = $edit ? (string) $edit['slug'] : '';
$parentValue = $edit ? (int) $edit['parent_id'] : 0;
?>
<div class="admin-page-head">
    <div>
        <h2>分类 / 标签</h2>
        <p>共 <?= $catTotal ?> 个分类、<?= $tagTotal ?> 个标签。</p>
    </div>
    <div>
        <a class="admin-btn<?= !$isTag ? ' admin-btn-primary' : '' ?>" href="<?= e(admin_url('terms/?taxonomy=category')) ?>">分类 (<?= $catTotal ?>)</a>
        <a class="admin-btn<?= $isTag ? ' admin-btn-primary' : '' ?>" href="<?= e(admin_url('terms/?taxonomy=post_tag')) ?>">标签 (<?= $tagTotal ?>)</a>
    </div>
</div>

<div class="admin-layout-3">
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2><?= $edit ? '编辑' . ($editTaxonomy === 'post_tag' ? '标签' : '分类') : '新增' . ($isTag ? '标签' : '分类') ?></h2>
            <?php if ($edit): ?><a class="admin-btn admin-btn-sm" href="<?= e(admin_url('terms/?taxonomy=' . $editTaxonomy)) ?>">取消编辑</a><?php endif; ?>
        </div>
        <form class="admin-form" action="<?= e(admin_url('terms/save/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <input type="hidden" name="taxonomy" value="<?= e($editTaxonomy) ?>">
            <div class="admin-form-row">
                <label for="term-name">名称</label>
                <input class="admin-input" type="text" id="term-name" name="name" value="<?= e($edit['name'] ?? '') ?>" required>
            </div>
            <div class="admin-form-row">
                <label for="term-slug">URL 别名</label>
                <input class="admin-input" type="text" id="term-slug" name="slug" value="<?= e($slugValue) ?>" placeholder="留空则根据名称生成">
                <span class="hint"><?= $editTaxonomy === 'post_tag' ? '/tag/' : '/category/' ?><?= e($slugValue !== '' ? $slugValue : '别名') ?>/</span>
            </div>
            <?php if ($editTaxonomy === 'category'): ?>
            <div class="admin-form-row">
                <label for="term-parent">父级分类</label>
                <select class="admin-select" id="term-parent" name="parent_id">
                    <option value="0">无（顶级分类）</option>
                    <?php foreach ($parents as $parent): ?>
                    <option value="<?= (int) $parent['id'] ?>"<?= $parentValue === (int) $parent['id'] ? ' selected' : '' ?>><?= e($parent['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="admin-form-row">
                <label for="term-description">描述</label>
                <textarea class="admin-textarea" id="term-description" name="description" style="min-height:100px;"><?= e($edit['description'] ?? '') ?></textarea>
            </div>
            <div><button class="admin-btn admin-btn-primary" type="submit"><?= $edit ? '保存修改' : '添加' ?></button></div>
        </form>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h2><?= $isTag ? '标签列表' : '分类列表' ?></h2></div>
        <?php if ($rows): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>名称</th>
                    <th>别名</th>
                    <?php if (!$isTag): ?><th>父级</th><?php endif; ?>
                    <th>数量</th>
                    <th>描述</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a class="row-title" href="<?= e(admin_url('terms/?taxonomy=' . $taxonomy . '&edit=' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                    </td>
                    <td class="admin-small admin-muted"><?= e($row['slug']) ?></td>
                    <?php if (!$isTag): ?>
                    <td class="admin-small"><?= (int) $row['parent_id'] > 0 ? e($nameById[(int) $row['parent_id']] ?? '—') : '<span class="admin-muted">顶级</span>' ?></td>
                    <?php endif; ?>
                    <td><?= (int) $row['count'] ?></td>
                    <td class="admin-small admin-muted"><?= e(mb_substr((string) $row['description'], 0, 60, 'UTF-8')) ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('terms/?taxonomy=' . $taxonomy . '&edit=' . (int) $row['id'])) ?>">编辑</a>
                            <?php if ((int) $row['count'] > 0): ?>
                            <a class="admin-btn admin-btn-sm" href="<?= e(term_url($row)) ?>" target="_blank" rel="noopener">查看</a>
                            <?php endif; ?>
                            <form action="<?= e(admin_url('terms/delete/')) ?>" method="post" data-confirm="确定删除“<?= e($row['name']) ?>”吗？相关文章将不再属于该<?= $isTag ? '标签' : '分类' ?>。">
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
        <div class="admin-empty">还没有<?= $isTag ? '标签' : '分类' ?>。</div>
        <?php endif; ?>
    </div>
</div>
