export type FieldType =
    | 'text'
    | 'email'
    | 'tel'
    | 'url'
    | 'date'
    | 'textarea'
    | 'radio'
    | 'chips'
    | 'select'
    | 'repeat'
    | 'color';

export interface HearingField {
    id: string;
    label: string;
    type: FieldType;
    options?: string[];
    placeholder?: string;
    /** 重点項目 */
    core?: boolean;
    wide?: boolean;
}

export interface HearingGroup {
    title: string;
    description: string;
    fields: HearingField[];
}

export interface HearingStep {
    title: string;
    subtitle: string;
    symbol: string;
    description: string;
    tip: string;
    groups: HearingGroup[];
}

type FieldExtra = Partial<Omit<HearingField, 'id' | 'label' | 'type'>>;

const F = (
    id: string,
    label: string,
    type: FieldType = 'text',
    extra: FieldExtra = {},
): HearingField => ({ id, label, type, ...extra });

export const HEARING_STEPS: HearingStep[] = [
    {
        title: '基本情報',
        subtitle: '会社・担当者・プロジェクト',
        symbol: '01',
        description: 'まずは、今回のプロジェクトについて教えてください。',
        tip: '連絡窓口と最終決裁者を分けて確認すると、制作中の確認がスムーズになります。',
        groups: [
            {
                title: 'プロジェクトについて',
                description: '今回つくるWebサイトの出発点を整理します。',
                fields: [
                    F('project', 'プロジェクト名', 'text', {
                        core: true,
                        placeholder: '例）コーポレートサイト リニューアル',
                        wide: true,
                    }),
                    F('type', 'ご相談の種類', 'radio', {
                        core: true,
                        options: [
                            '新規制作',
                            'リニューアル',
                            '一部改修',
                            '相談して決めたい',
                        ],
                        wide: true,
                    }),
                    F('siteType', 'Webサイトの種類', 'chips', {
                        options: [
                            'コーポレート',
                            'サービスサイト',
                            '店舗・施設',
                            '採用サイト',
                            'LP',
                            'ECサイト',
                            'その他',
                        ],
                        wide: true,
                    }),
                    F('hearingDate', 'ヒアリング日', 'date'),
                    F('interviewer', 'ヒアリング担当者', 'text', {
                        placeholder: '例）制作側の担当者名',
                    }),
                ],
            },
            {
                title: '会社・ご担当者',
                description:
                    'ご連絡先と、プロジェクトに関わる方を教えてください。',
                fields: [
                    F('company', '会社名 / 屋号', 'text', {
                        core: true,
                        placeholder: '例）株式会社〇〇',
                    }),
                    F('person', 'ご担当者名', 'text', {
                        core: true,
                        placeholder: '例）山田 太郎',
                    }),
                    F('department', '部署・役職', 'text', {
                        placeholder: '例）広報部 / マネージャー',
                    }),
                    F('email', 'メールアドレス', 'email', {
                        core: true,
                        placeholder: 'example@company.jp',
                    }),
                    F('phone', '電話番号', 'tel', {
                        placeholder: '例）03-1234-5678',
                    }),
                    F('website', '現在のWebサイト', 'url', {
                        placeholder: 'https://example.com',
                    }),
                    F('contact', '希望の連絡方法', 'radio', {
                        options: [
                            'メール',
                            'Chatwork',
                            'Slack',
                            'LINE',
                            '電話',
                            'その他',
                        ],
                        wide: true,
                    }),
                    F(
                        'contactDetail',
                        '連絡先URL・連絡可能な時間帯',
                        'textarea',
                        {
                            placeholder: '例）ChatworkのURL、平日10〜17時など',
                            wide: true,
                        },
                    ),
                    F('decision', '決裁者・確認フロー', 'textarea', {
                        core: true,
                        placeholder:
                            '例）担当者確認 → 代表が最終承認。通常3営業日以内に回答。',
                        wide: true,
                    }),
                ],
            },
        ],
    },
    {
        title: '事業と課題',
        subtitle: '事業内容・強み・現状',
        symbol: '02',
        description: '事業の魅力と、いま感じている課題を掘り下げます。',
        tip: '「強み」の根拠となる数字や、お客様の言葉も聞いてみましょう。',
        groups: [
            {
                title: '事業を知る',
                description: '何を、誰に、どんな価値として届けていますか？',
                fields: [
                    F('business', '主な事業・サービス', 'textarea', {
                        core: true,
                        placeholder:
                            '事業内容、主力商品、サービスの特徴を教えてください。',
                        wide: true,
                    }),
                    F('area', '商圏・対応エリア', 'text', {
                        placeholder: '例）奈良県全域 / 全国オンライン対応',
                    }),
                    F('price', '主な商品の価格帯', 'text', {
                        placeholder: '例）月額3万円〜 / 客単価5,000円',
                    }),
                    F('strength', 'お客様に選ばれている理由', 'textarea', {
                        core: true,
                        placeholder:
                            '他社との違い、その根拠となる実績・数字・お客様の声は？',
                        wide: true,
                    }),
                    F('inquiries', 'よくある相談・購入前の不安', 'textarea', {
                        placeholder:
                            '例）費用の総額がわからない、初めてでも利用できるか',
                        wide: true,
                    }),
                    F('competitors', '競合と感じる会社・サイト', 'repeat', {
                        placeholder: '会社名・URL・比較して気になる点',
                        wide: true,
                    }),
                ],
            },
            {
                title: '現状と改善したいこと',
                description:
                    '制作を考えたきっかけから、解決すべきことを見つけます。',
                fields: [
                    F('trigger', '制作・リニューアルのきっかけ', 'textarea', {
                        core: true,
                        placeholder:
                            'なぜ今、Webサイトをつくろうと思いましたか？',
                        wide: true,
                    }),
                    F('issues', '現在の課題', 'chips', {
                        options: [
                            '問い合わせが少ない',
                            '魅力が伝わらない',
                            '情報が古い',
                            '更新しづらい',
                            'スマホで見づらい',
                            '採用につながらない',
                            '検索で見つからない',
                            'その他',
                        ],
                        wide: true,
                    }),
                    F('metrics', '現在のアクセス・成果', 'textarea', {
                        placeholder:
                            '月間アクセス、問い合わせ数、成約率など。未計測ならその旨を記入。',
                        wide: true,
                    }),
                    F(
                        'keep',
                        '引き継ぎたいもの・変えたくないこと',
                        'textarea',
                        {
                            placeholder:
                                '既存のURL、検索順位、掲載情報、ブランドの印象など',
                            wide: true,
                        },
                    ),
                ],
            },
        ],
    },
    {
        title: '目的とターゲット',
        subtitle: 'ゴール・届けたい相手',
        symbol: '03',
        description: '誰の、どんな行動につながるWebサイトにするかを決めます。',
        tip: '目的が複数ある場合は、最も優先したいものを1つ決めるのがおすすめです。',
        groups: [
            {
                title: 'Webサイトのゴール',
                description: '公開することの、その先にある成果を言葉にします。',
                fields: [
                    F('goal', '最も優先する目的', 'radio', {
                        core: true,
                        options: [
                            '問い合わせ獲得',
                            '商品・サービスの販売',
                            '認知・信頼の向上',
                            '採用応募',
                            '来店・予約',
                            '情報提供',
                        ],
                        wide: true,
                    }),
                    F('success', '成功と言える状態・目標数値', 'textarea', {
                        core: true,
                        placeholder:
                            '例）公開6か月後に、月の問い合わせを5件から15件へ。',
                        wide: true,
                    }),
                    F('action', '訪問者にとってほしい行動', 'chips', {
                        options: [
                            'フォーム送信',
                            '電話',
                            'LINE登録',
                            '予約',
                            '購入',
                            '資料請求',
                            '応募',
                            'その他',
                        ],
                        wide: true,
                    }),
                    F('priority', '今回実現したいことの優先順位', 'textarea', {
                        placeholder:
                            '1. 必ず実現したいこと\n2. できれば実現したいこと\n3. 将来取り組みたいこと',
                        wide: true,
                    }),
                ],
            },
            {
                title: '届けたいお客様',
                description:
                    'ひとりのお客様を思い浮かべると、伝え方が具体的になります。',
                fields: [
                    F('audience', '主なターゲット像', 'textarea', {
                        core: true,
                        placeholder:
                            '年齢層・地域・職業・ライフスタイル。法人なら業種・規模・担当者の役割。',
                        wide: true,
                    }),
                    F('needs', 'ターゲットの悩み・求めていること', 'textarea', {
                        core: true,
                        placeholder:
                            'どんな困りごとがあり、何を解決するためにサイトに訪れますか？',
                        wide: true,
                    }),
                    F('discovery', 'サイトを知るきっかけ', 'chips', {
                        options: [
                            '検索',
                            'Instagram',
                            'X',
                            'YouTube',
                            '広告',
                            '紹介',
                            'チラシ・名刺',
                            'その他',
                        ],
                        wide: true,
                    }),
                    F('keywords', '検索しそうなキーワード', 'text', {
                        placeholder: '例）奈良 注文住宅 / 自然素材 家',
                        wide: true,
                    }),
                    F(
                        'barriers',
                        '比較検討のポイント・申し込みの障壁',
                        'textarea',
                        {
                            placeholder:
                                '価格、実績、立地、安心感など。最後のひと押しに必要な情報は？',
                            wide: true,
                        },
                    ),
                ],
            },
        ],
    },
    {
        title: 'デザイン',
        subtitle: '雰囲気・配色・参考サイト',
        symbol: '04',
        description: '「なんとなく」の好みを、共有できるイメージにします。',
        tip: '参考サイトは「好きな理由」まで確認しましょう。色・余白・写真・動きなどに分けると伝わります。',
        groups: [
            {
                title: '伝えたい印象',
                description: 'ブランドらしさと、訪問者に感じてほしいこと。',
                fields: [
                    F('mood', '希望する雰囲気', 'chips', {
                        core: true,
                        options: [
                            'やさしい',
                            'あたたかい',
                            '信頼・誠実',
                            '爽やか',
                            '力強い',
                            '上品・大人',
                            '元気・ポップ',
                            '自然・ナチュラル',
                            'モダン・クール',
                            'シンプル',
                        ],
                        wide: true,
                    }),
                    F('impression', '最初の3秒で伝えたいこと', 'textarea', {
                        placeholder:
                            '例）地域に根ざした、気軽に相談できる専門家であること。',
                        wide: true,
                    }),
                    F('mainColor', 'ブランドカラー', 'color', {
                        placeholder: '#111827',
                    }),
                    F('accentColor', 'アクセントカラー', 'color', {
                        placeholder: '#8CB64A',
                    }),
                    F('brandRules', 'ロゴ・ブランドのルール', 'textarea', {
                        placeholder:
                            '既存ロゴの有無、指定フォント、使ってはいけない色など',
                        wide: true,
                    }),
                    F('avoid', '避けたい雰囲気・表現', 'textarea', {
                        placeholder:
                            '例）派手なアニメーション、堅すぎる文章、安売りの印象',
                        wide: true,
                    }),
                ],
            },
            {
                title: '参考サイト',
                description:
                    'URLとあわせて、どこを参考にしたいかを記録します。',
                fields: [
                    F('references', '参考にしたいサイトとその理由', 'repeat', {
                        core: true,
                        placeholder:
                            'https://… / 好きなところ・取り入れたい理由',
                        wide: true,
                    }),
                    F('visuals', '写真・イラスト・動きの希望', 'textarea', {
                        placeholder:
                            '例）実際のスタッフの写真を使いたい。動きは控えめに。',
                        wide: true,
                    }),
                ],
            },
        ],
    },
    {
        title: '機能と素材',
        subtitle: 'ページ構成・機能・コンテンツ',
        symbol: '05',
        description: '必要なページと機能、制作に使う素材を整理します。',
        tip: '機能は「必須」と「将来」で分け、原稿・写真それぞれの準備担当も確認しましょう。',
        groups: [
            {
                title: 'ページと機能',
                description: 'お客様の行動につながる情報と仕組みを考えます。',
                fields: [
                    F('pages', '必要なページ', 'chips', {
                        core: true,
                        options: [
                            'トップ',
                            '会社案内',
                            'サービス紹介',
                            '料金',
                            '実績・事例',
                            'お客様の声',
                            'よくある質問',
                            'お知らせ',
                            'ブログ',
                            '採用',
                            'アクセス',
                            'お問い合わせ',
                            'プライバシーポリシー',
                        ],
                        wide: true,
                    }),
                    F(
                        'pageNotes',
                        'ページ構成の補足・想定ページ数',
                        'textarea',
                        {
                            placeholder:
                                '例）サービス紹介は3ページ、事例は初期10件。追加ページの希望も。',
                            wide: true,
                        },
                    ),
                    F('features', '必要な機能', 'chips', {
                        core: true,
                        options: [
                            'お問い合わせフォーム',
                            '予約',
                            'オンライン決済',
                            '会員登録',
                            'サイト内検索',
                            '多言語',
                            'ブログ更新',
                            'SNS連携',
                            '資料ダウンロード',
                            '特になし',
                        ],
                        wide: true,
                    }),
                    F('featurePriority', '機能の優先順位・詳細', 'textarea', {
                        placeholder:
                            '必須機能と後から追加できる機能、利用中の外部サービス・連携条件など',
                        wide: true,
                    }),
                    F(
                        'formDetails',
                        'フォームの項目・送信後の流れ',
                        'textarea',
                        {
                            placeholder:
                                '必要な入力項目、通知先、受付後の対応者、返信の目安など',
                            wide: true,
                        },
                    ),
                    F('languages', '対応言語・翻訳担当', 'text', {
                        placeholder: '例）日本語・英語 / 翻訳は依頼したい',
                        wide: true,
                    }),
                ],
            },
            {
                title: '素材とコンテンツ',
                description: '「誰が・いつまでに用意するか」まで確認します。',
                fields: [
                    F('assets', '用意できる素材', 'chips', {
                        options: [
                            'ロゴデータ',
                            '写真',
                            '動画',
                            '原稿',
                            'パンフレット',
                            '実績資料',
                            'まだない',
                        ],
                        wide: true,
                    }),
                    F('assetOwner', '素材・原稿の準備担当と期限', 'textarea', {
                        core: true,
                        placeholder:
                            '例）原稿は広報が11月末までに用意。写真は撮影を依頼したい。',
                        wide: true,
                    }),
                    F('rights', '掲載許可・素材の権利確認', 'textarea', {
                        placeholder:
                            '写真の人物・顧客事例の掲載許可、素材の利用範囲など',
                        wide: true,
                    }),
                    F(
                        'accessibility',
                        '閲覧環境・アクセシビリティの配慮',
                        'textarea',
                        {
                            placeholder:
                                '想定端末、文字の大きさ、色の見分けやすさ、キーボード操作など',
                            wide: true,
                        },
                    ),
                ],
            },
        ],
    },
    {
        title: '予算と運用',
        subtitle: 'スケジュール・公開後・次の一歩',
        symbol: '06',
        description: '制作の進め方と、公開したあとの育て方を確認します。',
        tip: '公開希望日だけでなく、その理由と素材の準備期限を確認すると、現実的な計画になります。',
        groups: [
            {
                title: '予算とスケジュール',
                description: '無理のない進行に向けて、条件を共有します。',
                fields: [
                    F('budget', '制作予算の目安', 'select', {
                        core: true,
                        options: [
                            '30万円未満',
                            '30〜50万円',
                            '50〜100万円',
                            '100〜200万円',
                            '200万円以上',
                            '相談して決めたい',
                        ],
                    }),
                    F('deadline', '希望公開日', 'date', { core: true }),
                    F('budgetScope', '予算に含めたいもの・上限', 'textarea', {
                        placeholder:
                            '税込 / 税別、撮影・原稿・保守費の扱い、上限の柔軟性など',
                        wide: true,
                    }),
                    F(
                        'deadlineReason',
                        '公開日の理由・変更の可否',
                        'textarea',
                        {
                            placeholder:
                                '例）新店舗オープンに合わせたい。告知開始の2週間前が必須。',
                            wide: true,
                        },
                    ),
                    F(
                        'reviewTime',
                        '確認に必要な日数・打ち合わせ頻度',
                        'text',
                        {
                            placeholder:
                                '例）確認は3営業日、隔週でオンライン打ち合わせ',
                            wide: true,
                        },
                    ),
                ],
            },
            {
                title: 'サーバーと公開後の運用',
                description: '管理する人と、続けられる仕組みを決めます。',
                fields: [
                    F('domain', 'ドメイン・サーバーの状況', 'textarea', {
                        core: true,
                        placeholder:
                            '取得状況、契約先、管理者、メールへの影響、移行の希望。パスワードは記入しないでください。',
                        wide: true,
                    }),
                    F('updates', '公開後の更新担当・頻度', 'textarea', {
                        core: true,
                        placeholder:
                            '誰が、何を、どのくらいの頻度で更新しますか？ 操作説明は必要ですか？',
                        wide: true,
                    }),
                    F('maintenance', '必要な運用サポート', 'chips', {
                        options: [
                            '自社で運用',
                            '更新代行',
                            'バックアップ',
                            'セキュリティ保守',
                            'アクセス解析',
                            'SEO改善',
                            '広告運用',
                            '相談したい',
                        ],
                        wide: true,
                    }),
                    F('runningCost', '月額運用予算', 'text', {
                        placeholder: '例）月額1〜3万円 / 未定',
                    }),
                    F('analytics', '計測ツール・アカウント管理者', 'text', {
                        placeholder: 'GA4、Search Consoleなど',
                    }),
                    F('policies', '公開前に確認が必要なルール', 'textarea', {
                        placeholder:
                            '個人情報の扱い、掲載表現の社内審査、業界の規定など',
                        wide: true,
                    }),
                ],
            },
            {
                title: '次の一歩',
                description:
                    '決まっていないことも、次のアクションにつなげます。',
                fields: [
                    F('nextAction', '次にすること・担当者・期限', 'textarea', {
                        core: true,
                        placeholder:
                            '例）11/5までに担当者がロゴと既存パンフレットを共有。制作側は概算見積もりを提出。',
                        wide: true,
                    }),
                    F('notes', 'その他のご要望・議事メモ', 'textarea', {
                        placeholder:
                            '気になっていることや、話し合いの中で決まったことを自由に。',
                        wide: true,
                    }),
                ],
            },
        ],
    },
];
