export type DummyFieldType =
    | 'id'
    | 'name'
    | 'email'
    | 'phone'
    | 'address'
    | 'company'
    | 'date'
    | 'number'
    | 'uuid'
    | 'boolean';

export interface DummyDataOptions {
    numberMin: number;
    numberMax: number;
    dateStartYear: number;
    dateEndYear: number;
}

export const DEFAULT_DUMMY_DATA_OPTIONS: DummyDataOptions = {
    numberMin: 0,
    numberMax: 100,
    dateStartYear: 2020,
    dateEndYear: 2026,
};

const LAST_NAMES = [
    '佐藤',
    '鈴木',
    '高橋',
    '田中',
    '伊藤',
    '渡辺',
    '山本',
    '中村',
    '小林',
    '加藤',
];

const FIRST_NAMES = [
    '翔太',
    '美咲',
    '大輔',
    '愛',
    '健太',
    '由美',
    '拓也',
    'あかり',
    '雄太',
    'さくら',
];

const PREFECTURES = [
    '東京都',
    '大阪府',
    '愛知県',
    '福岡県',
    '北海道',
    '神奈川県',
    '埼玉県',
    '千葉県',
    '兵庫県',
    '京都府',
];

const CITIES = [
    '中央区',
    '港区',
    '西区',
    '北区',
    '南区',
    '東区',
    '緑区',
    '旭区',
    '栄区',
    '若葉区',
];

const COMPANIES = [
    '株式会社サンプル',
    '合同会社テスト',
    '有限会社デモ',
    '株式会社イノベート',
    '株式会社クリエイト',
    '合同会社ネクスト',
    '株式会社スタイル',
    '有限会社パートナーズ',
    '株式会社ビジョン',
    '合同会社リンク',
];

const EMAIL_DOMAINS = [
    'example.com',
    'test-mail.jp',
    'sample.co.jp',
    'mail-demo.com',
    'dummy.jp',
];

function pickRandom<T>(pool: T[]): T {
    return pool[Math.floor(Math.random() * pool.length)];
}

function randomDigits(length: number): string {
    let result = '';

    for (let i = 0; i < length; i++) {
        result += Math.floor(Math.random() * 10).toString();
    }

    return result;
}

export function generateName(): string {
    return `${pickRandom(LAST_NAMES)} ${pickRandom(FIRST_NAMES)}`;
}

export function generateEmail(): string {
    const local = `user${randomDigits(4)}`;

    return `${local}@${pickRandom(EMAIL_DOMAINS)}`;
}

export function generatePhone(): string {
    return `0${randomDigits(2)}-${randomDigits(4)}-${randomDigits(4)}`;
}

export function generateAddress(): string {
    const blockNumber = 1 + Math.floor(Math.random() * 9);
    const lotNumber = 1 + Math.floor(Math.random() * 30);
    const unitNumber = 1 + Math.floor(Math.random() * 20);

    return `${pickRandom(PREFECTURES)}${pickRandom(CITIES)}${blockNumber}-${lotNumber}-${unitNumber}`;
}

export function generateCompany(): string {
    return pickRandom(COMPANIES);
}

export function generateDate(startYear: number, endYear: number): string {
    const year =
        startYear + Math.floor(Math.random() * (endYear - startYear + 1));
    const month = 1 + Math.floor(Math.random() * 12);
    const day = 1 + Math.floor(Math.random() * 28);

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function generateNumber(min: number, max: number): number {
    return min + Math.floor(Math.random() * (max - min + 1));
}

export function generateUuid(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}

export function generateBoolean(): boolean {
    return Math.random() < 0.5;
}

export function generateDummyData(
    fields: DummyFieldType[],
    count: number,
    options: DummyDataOptions = DEFAULT_DUMMY_DATA_OPTIONS,
): Record<string, unknown>[] {
    const records: Record<string, unknown>[] = [];

    for (let index = 0; index < count; index++) {
        const record: Record<string, unknown> = {};

        for (const field of fields) {
            switch (field) {
                case 'id':
                    record.id = index + 1;
                    break;
                case 'name':
                    record.name = generateName();
                    break;
                case 'email':
                    record.email = generateEmail();
                    break;
                case 'phone':
                    record.phone = generatePhone();
                    break;
                case 'address':
                    record.address = generateAddress();
                    break;
                case 'company':
                    record.company = generateCompany();
                    break;
                case 'date':
                    record.date = generateDate(
                        options.dateStartYear,
                        options.dateEndYear,
                    );
                    break;
                case 'number':
                    record.number = generateNumber(
                        options.numberMin,
                        options.numberMax,
                    );
                    break;
                case 'uuid':
                    record.uuid = generateUuid();
                    break;
                case 'boolean':
                    record.boolean = generateBoolean();
                    break;
            }
        }

        records.push(record);
    }

    return records;
}
