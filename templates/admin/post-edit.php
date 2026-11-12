<?php
$base   = $type === 'page' ? 'pages' : 'posts';
$isPage = $type === 'page';
$createdValue = $post ? date('Y-m-d\TH:i', strtotime((string) $post['created_at'])) : date('Y-m-d\TH:i');
$coverId = (int) ($post['cover_id'] ?? 0);
$coverUrl = $coverId > 0 ? media_url($coverId) : '';
$tagsValue = implode(', ', $selectedTags);
$statusValue = (string) ($post['status'] ?? 'publish');
$editorVer = is_file(SYH_ROOT . '/public/assets/admin-editor.js') ? (string) filemtime(SYH_ROOT . '/public/assets/admin-editor.js') : '1';
$postId = (int) ($post['id'] ?? 0);
?>
<div class="admin-page-head">
    <div>
        <h2><?= e($title) ?></h2>
        <p><?= $post ? '最后更新：' . e(format_date((string) $post['updated_at'], 'Y-m-d H:i')) : '创建新的' . ($isPage ? '页面' : '文章') . '。' ?></p>
    </div>
    <div>
        <?php if ($post && $post['status'] === 'publish'): ?>
        <a class="admin-btn" href="<?= e($isPage ? '/' . rawurlencode((string) $post['slug']) . '/' : post_url($post)) ?>" target="_blank" rel="noopener">查看</a>
        <?php endif; ?>
        <a class="admin-btn" href="<?= e(admin_url($base . '/')) ?>">返回列表</a>
    </div>
</div>

<div class="syh-restore" id="syh-restore" hidden>
    <span>发现这份内容在本机的自动保存草稿。</span>
    <button type="button" class="admin-btn admin-btn-sm" id="syh-restore-yes">恢复草稿</button>
    <button type="button" class="admin-btn admin-btn-sm" id="syh-restore-no">忽略</button>
</div>

<form class="admin-layout-2" action="<?= e(admin_url($base . '/save/')) ?>" method="post" id="post-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="id" value="<?= $postId ?>">

    <div>
        <div class="admin-panel">
            <div class="admin-form">
                <div class="admin-form-row">
                    <label for="title">标题</label>
                    <input class="admin-input admin-title-input" type="text" id="title" name="title" value="<?= e($post['title'] ?? '') ?>" placeholder="输入标题…" required autofocus>
                </div>
                <div class="admin-form-row">
                    <label for="slug">固定链接</label>
                    <div class="admin-permalink">
                        <span><?= e(site_url()) ?>/</span>
                        <input class="admin-input" type="text" id="slug" name="slug" value="<?= e($post['slug'] ?? '') ?>" data-auto="<?= $post ? '0' : '1' ?>" placeholder="留空则根据标题生成">
                        <span>/</span>
                    </div>
                    <span class="hint">文章地址：<?= e(site_url()) ?>/<span id="slug-preview"><?= e($post['slug'] ?? '别名') ?></span>/</span>
                </div>

                <div class="admin-form-row">
                    <label>正文</label>
                    <div class="syh-editor" id="syh-editor" data-post-id="<?= $postId ?>">
                        <div class="syh-editor-tabs">
                            <button type="button" class="syh-tab is-active" data-mode="visual">可视化</button>
                            <button type="button" class="syh-tab" data-mode="html">HTML</button>
                            <span class="syh-editor-spacer"></span>
                            <span class="syh-editor-count" data-editor-count>0 字</span>
                        </div>
                        <div class="syh-editor-toolbar" role="toolbar" aria-label="格式工具栏">
                            <select class="syh-tool-select" data-format aria-label="段落格式">
                                <option value="p">段落</option>
                                <option value="h2">标题 2</option>
                                <option value="h3">标题 3</option>
                                <option value="h4">标题 4</option>
                                <option value="pre">代码块</option>
                            </select>
                            <span class="syh-tool-sep"></span>
                            <button type="button" class="syh-tool" data-cmd="bold" title="加粗 (Ctrl+B)"><strong>B</strong></button>
                            <button type="button" class="syh-tool" data-cmd="italic" title="斜体 (Ctrl+I)"><em>I</em></button>
                            <button type="button" class="syh-tool" data-cmd="strikeThrough" title="删除线"><span style="text-decoration:line-through">S</span></button>
                            <span class="syh-tool-sep"></span>
                            <button type="button" class="syh-tool" data-cmd="formatBlock" data-value="blockquote" title="引用">&ldquo;</button>
                            <button type="button" class="syh-tool" data-cmd="insertUnorderedList" title="无序列表">•</button>
                            <button type="button" class="syh-tool" data-cmd="insertOrderedList" title="有序列表">1.</button>
                            <span class="syh-tool-sep"></span>
                            <button type="button" class="syh-tool" data-cmd="link" title="插入链接 (Ctrl+K)">链接</button>
                            <button type="button" class="syh-tool" data-cmd="unlink" title="移除链接">断开</button>
                            <button type="button" class="syh-tool" data-cmd="image" title="插入图片">图片</button>
                            <button type="button" class="syh-tool" data-cmd="inlineCode" title="行内代码">&lt;/&gt;</button>
                            <button type="button" class="syh-tool" data-cmd="insertHorizontalRule" title="分隔线">—</button>
                            <span class="syh-tool-sep"></span>
                            <button type="button" class="syh-tool" data-cmd="undo" title="撤销 (Ctrl+Z)">↶</button>
                            <button type="button" class="syh-tool" data-cmd="redo" title="重做 (Ctrl+Shift+Z)">↷</button>
                            <button type="button" class="syh-tool" data-cmd="removeFormat" title="清除格式">清除</button>
                            <span class="syh-editor-spacer"></span>
                            <button type="button" class="syh-tool" data-cmd="preview" title="预览">预览</button>
                            <button type="button" class="syh-tool" data-cmd="expand" title="全屏">⛶</button>
                        </div>
                        <div class="syh-editor-visual" id="syh-visual" contenteditable="true" data-placeholder="从这里开始写文章…"><?= $post['content'] ?? '' ?></div>
                        <textarea class="syh-editor-source" id="content" name="content" spellcheck="false" wrap="off"><?= e($post['content'] ?? '') ?></textarea>
                        <div class="syh-editor-foot">
                            <span>支持直接粘贴图片/网页内容 · 可视化与 HTML 可随时切换</span>
                            <span>快捷键：Ctrl/⌘ + S 保存</span>
                        </div>
                    </div>
                </div>

                <div class="admin-form-row">
                    <label for="excerpt">摘要</label>
                    <textarea class="admin-textarea" id="excerpt" name="excerpt" style="min-height:90px;" placeholder="用一两句话概括内容，留空则自动截取正文。"><?= e($post['excerpt'] ?? '') ?></textarea>
                    <span class="hint"><span id="excerpt-count">0</span> 字 · 留空时自动从正文截取。</span>
                </div>
            </div>
        </div>
    </div>

    <aside>
        <div class="admin-panel">
            <div class="admin-panel-head"><h2>发布</h2></div>
            <div class="admin-form">
                <div class="admin-form-row">
                    <label for="status">状态</label>
                    <select class="admin-select" id="status" name="status">
                        <?php foreach (['publish' => '已发布', 'draft' => '草稿', 'pending' => '待审核', 'private' => '私密'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $statusValue === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-row">
                    <label for="created_at">发布时间</label>
                    <input class="admin-input" type="datetime-local" id="created_at" name="created_at" value="<?= e($createdValue) ?>">
                </div>
                <div class="admin-form-row">
                    <label for="comment_status">评论</label>
                    <select class="admin-select" id="comment_status" name="comment_status">
                        <option value="open"<?= ($post['comment_status'] ?? 'open') === 'open' ? ' selected' : '' ?>>允许评论</option>
                        <option value="closed"<?= ($post['comment_status'] ?? 'open') === 'closed' ? ' selected' : '' ?>>关闭评论</option>
                    </select>
                </div>
                <?php if ($isPage): ?>
                <div class="admin-form-row">
                    <label for="template">页面模板</label>
                    <select class="admin-select" id="template" name="template">
                        <?php foreach (['' => '默认', 'page-stats.php' => '统计页', 'page-archive.php' => '归档页', 'page-guestbook.php' => '留言板', 'page-links.php' => '友情链接'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($post['template'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-row">
                    <label for="menu_order">排序</label>
                    <input class="admin-input" type="number" id="menu_order" name="menu_order" min="0" value="<?= (int) ($post['menu_order'] ?? 0) ?>">
                </div>
                <?php endif; ?>
                <div class="admin-publish-actions">
                    <button class="admin-btn admin-btn-primary" type="submit" id="publish-btn" data-save="main"><?= $statusValue === 'publish' ? '更新' : '保存' ?></button>
                    <button class="admin-btn" type="submit" data-save="draft">存为草稿</button>
                    <button class="admin-btn" type="button" id="syh-preview-btn">预览</button>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><h2>特色图片</h2></div>
            <div class="admin-form-row">
                <select class="admin-select" id="cover_id" name="cover_id">
                    <option value="0" data-url="">不使用</option>
                    <?php foreach ($mediaItems as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" data-url="<?= e(media_url_by_file((string) $m['file'])) ?>"<?= $coverId === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['title'] !== '' ? $m['title'] : $m['file']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="admin-cover-preview">
                    <img id="cover-preview" src="<?= e($coverUrl) ?>" alt=""<?= $coverUrl === '' ? ' style="display:none"' : '' ?>>
                </div>
                <button type="button" class="admin-btn admin-btn-sm" id="cover-pick" style="margin-top:10px;">从媒体库选择</button>
            </div>
        </div>

        <?php if (!$isPage): ?>
        <div class="admin-panel">
            <div class="admin-panel-head"><h2>分类</h2></div>
            <?php if ($cats): ?>
            <div class="admin-check-list">
                <?php foreach ($cats as $cat): ?>
                <label class="admin-check">
                    <input type="checkbox" name="categories[]" value="<?= (int) $cat['id'] ?>"<?= in_array((int) $cat['id'], $selectedCats, true) ? ' checked' : '' ?>>
                    <span><?= e($cat['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="admin-empty">还没有分类。</div>
            <?php endif; ?>
            <div class="admin-form-row" style="margin-top:14px;">
                <label for="tags">标签</label>
                <input class="admin-input" type="text" id="tags" name="tags" value="<?= e($tagsValue) ?>" placeholder="用逗号分隔" list="tag-suggestions" autocomplete="off">
                <datalist id="tag-suggestions">
                    <?php foreach ($tags as $tag): ?><option value="<?= e($tag['name']) ?>"></option><?php endforeach; ?>
                </datalist>
                <span class="hint">输入新名称会自动创建标签。</span>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</form>

<div class="syh-modal" id="syh-media-modal" hidden>
    <div class="syh-modal-card">
        <div class="syh-modal-head">
            <h3>媒体库</h3>
            <button type="button" class="admin-btn admin-btn-sm" data-close>关闭</button>
        </div>
        <div class="syh-modal-body">
            <input class="admin-input" type="search" id="syh-media-search" placeholder="搜索图片…">
            <div class="syh-media-grid" id="syh-media-grid"></div>
            <p class="hint" style="margin:12px 0 0;">找不到？<a href="<?= e(admin_url('media/')) ?>" target="_blank" rel="noopener">去媒体库上传</a>。</p>
        </div>
    </div>
</div>

<div class="syh-modal" id="syh-preview-modal" hidden>
    <div class="syh-modal-card">
        <div class="syh-modal-head">
            <h3>预览</h3>
            <button type="button" class="admin-btn admin-btn-sm" data-close>关闭</button>
        </div>
        <div class="syh-modal-body">
            <div class="syh-preview-body" id="syh-preview-body"></div>
        </div>
    </div>
</div>

<script>window.SYH_MEDIA = <?= json_encode($editorMedia, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/assets/admin-editor.js?v=<?= e($editorVer) ?>"></script>
