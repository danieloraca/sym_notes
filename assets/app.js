import './stimulus_bootstrap.js';

const folderTreeStorageKey = 'sym-notes.folder-tree-state';

function readFolderTreeState() {
    try {
        const storedState = localStorage.getItem(folderTreeStorageKey);

        if (!storedState) {
            return {};
        }

        const state = JSON.parse(storedState);

        return state && typeof state === 'object' ? state : {};
    } catch {
        return {};
    }
}

function saveFolderTreeState(state) {
    try {
        localStorage.setItem(folderTreeStorageKey, JSON.stringify(state));
    } catch {
        // Storage can be unavailable in private browsing or restricted contexts.
    }
}

function initializeFolderTrees() {
    const savedState = readFolderTreeState();

    document.querySelectorAll('[data-folder-tree]').forEach((tree) => {
        tree.querySelectorAll('[data-folder-id]').forEach((branch) => {
            const folderId = branch.dataset.folderId;

            if (Object.hasOwn(savedState, folderId)) {
                branch.open = savedState[folderId] === true;
            }

            if (branch.dataset.folderStateBound === 'true') {
                return;
            }

            branch.dataset.folderStateBound = 'true';
            branch.addEventListener('toggle', () => {
                const currentState = readFolderTreeState();
                currentState[folderId] = branch.open;
                saveFolderTreeState(currentState);
            });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFolderTrees, {once: true});
} else {
    initializeFolderTrees();
}

document.addEventListener('turbo:load', initializeFolderTrees);
