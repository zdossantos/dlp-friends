import { describe, expect, test } from 'bun:test';
import {
    isUserDataExportPreparing,
    userDataExportBecameReady,
} from '../../resources/js/lib/userDataExport';

describe('personal data export state', () => {
    test('keeps the request disabled while preparation is active', () => {
        expect(isUserDataExportPreparing('pending')).toBe(true);
        expect(isUserDataExportPreparing('processing')).toBe(true);
        expect(isUserDataExportPreparing('ready')).toBe(false);
        expect(isUserDataExportPreparing('failed')).toBe(false);
        expect(isUserDataExportPreparing(undefined)).toBe(false);
    });

    test('announces only a transition from preparation to ready', () => {
        expect(userDataExportBecameReady('processing', 'ready')).toBe(true);
        expect(userDataExportBecameReady('pending', 'ready')).toBe(true);
        expect(userDataExportBecameReady('ready', 'ready')).toBe(false);
        expect(userDataExportBecameReady(undefined, 'ready')).toBe(false);
        expect(userDataExportBecameReady('processing', 'failed')).toBe(false);
    });
});
