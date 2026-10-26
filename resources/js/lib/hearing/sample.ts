import type { SheetState } from './sheet';

/** 「サンプルを見る」で読み込む、架空の工務店のヒアリング例 */
export const SAMPLE_STATE: SheetState = {
    version: 1,
    step: 0,
    answers: {
        project: 'こもれび工務店 コーポレートサイト',
        type: 'リニューアル',
        siteType: ['コーポレート'],
        company: '株式会社こもれび工務店（サンプル）',
        person: '山田 花子',
        department: '広報・マーケティング',
        email: 'sample@example.com',
        website: 'https://example.com',
        contact: 'メール',
        decision: '広報担当が確認後、代表が最終承認。確認期間は3営業日。',
        business:
            '奈良県で自然素材の注文住宅を設計・施工。家族が長く心地よく暮らせる住まいを提供しています。',
        area: '奈良県北部',
        price: '注文住宅 2,500万円〜',
        strength:
            '創業30年。設計から施工まで自社対応。年間12棟に絞り、一組ずつ丁寧に向き合います。',
        trigger:
            '施工事例の更新が難しく、住まいづくりの想いが伝わっていないため。',
        issues: ['魅力が伝わらない', '更新しづらい'],
        goal: '問い合わせ獲得',
        success: '公開6か月後に、見学会の予約を月3件から10件へ増やしたい。',
        action: ['予約', '資料請求'],
        audience:
            '奈良県在住の30〜40代、子育て中の共働き世帯。自然素材の家に関心がある方。',
        needs: '予算内で、家族が安心して長く暮らせる家を建てたい。',
        mood: ['あたたかい', '自然・ナチュラル', '信頼・誠実'],
        mainColor: '#526B4D',
        accentColor: '#C9AA79',
        pages: [
            'トップ',
            '会社案内',
            'サービス紹介',
            '実績・事例',
            'お客様の声',
            'お問い合わせ',
        ],
        features: ['お問い合わせフォーム', '予約', 'ブログ更新'],
        assets: ['ロゴデータ', '写真', 'パンフレット'],
        assetOwner:
            '広報担当が原稿を準備。施工写真は既存素材を整理し、不足分は撮影を依頼。',
        budget: '50〜100万円',
        updates: '広報担当が月2回、施工事例と見学会のお知らせを更新。',
        nextAction:
            '制作側：概算見積もりを提出。広報担当：掲載したい施工事例を5件選定。',
    },
    pending: { deadline: true, domain: true, references: true },
    updatedAt: null,
};
