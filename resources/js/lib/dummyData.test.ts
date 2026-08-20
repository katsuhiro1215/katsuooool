import { describe, expect, test } from 'vitest';

import {
    generateAddress,
    generateBoolean,
    generateCompany,
    generateDate,
    generateDummyData,
    generateEmail,
    generateName,
    generateNumber,
    generatePhone,
    generateUuid,
} from './dummyData';

describe('generateName', () => {
    test('returns a non-empty "姓 名" string', () => {
        const name = generateName();

        expect(name).toMatch(/^\S+ \S+$/);
    });
});

describe('generateEmail', () => {
    test('returns a syntactically valid email address', () => {
        const email = generateEmail();

        expect(email).toMatch(/^[a-z0-9]+@[a-z0-9.-]+\.[a-z]{2,}$/);
    });
});

describe('generatePhone', () => {
    test('returns a phone number in 0XX-XXXX-XXXX format', () => {
        const phone = generatePhone();

        expect(phone).toMatch(/^0\d{2}-\d{4}-\d{4}$/);
    });
});

describe('generateAddress', () => {
    test('returns a non-empty Japanese address string', () => {
        const address = generateAddress();

        expect(address.length).toBeGreaterThan(0);
        expect(address).toMatch(/^\S+[都道府県]/);
    });
});

describe('generateCompany', () => {
    test('returns a non-empty company name', () => {
        expect(generateCompany().length).toBeGreaterThan(0);
    });
});

describe('generateDate', () => {
    test('returns an ISO date string within the given year range', () => {
        const date = generateDate(2024, 2024);

        expect(date).toMatch(/^2024-\d{2}-\d{2}$/);
    });
});

describe('generateNumber', () => {
    test('returns min when min equals max', () => {
        expect(generateNumber(5, 5)).toBe(5);
    });

    test('returns a value within the inclusive range', () => {
        const value = generateNumber(1, 3);

        expect(value).toBeGreaterThanOrEqual(1);
        expect(value).toBeLessThanOrEqual(3);
    });
});

describe('generateUuid', () => {
    test('returns a valid UUID v4 string', () => {
        const uuid = generateUuid();

        expect(uuid).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
        );
    });
});

describe('generateBoolean', () => {
    test('returns a boolean', () => {
        expect(typeof generateBoolean()).toBe('boolean');
    });
});

describe('generateDummyData', () => {
    test('returns the requested number of records with a sequential id field', () => {
        const records = generateDummyData(['id', 'name'], 3);

        expect(records).toHaveLength(3);
        expect(records[0].id).toBe(1);
        expect(records[1].id).toBe(2);
        expect(records[2].id).toBe(3);
        expect(typeof records[0].name).toBe('string');
    });

    test('only includes the selected fields as object keys', () => {
        const records = generateDummyData(['email', 'phone'], 1);

        expect(Object.keys(records[0]).sort()).toEqual(['email', 'phone']);
    });

    test('returns an empty array when count is 0', () => {
        expect(generateDummyData(['id'], 0)).toEqual([]);
    });
});
