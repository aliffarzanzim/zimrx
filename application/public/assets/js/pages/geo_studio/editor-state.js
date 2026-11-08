export function createEditorState() {
    return {
        dataset: null,
        datasetPackId: '',
        datasetSource: '',
        datasetRevision: '',
        saving: false,
        packId: '',
        source: 'system',
        selectedNode: null,
        selectedRoot: false,
        selectedPath: [],
        expandedKeys: new Set(),
        isDirty: false,
        revision: 0,
        setDirty(dirty) {
            this.isDirty = Boolean(dirty);
            if (dirty) this.revision++;
        },
    };
}
