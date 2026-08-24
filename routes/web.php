<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\Tools\Base64EncodeController;
use App\Http\Controllers\Tools\ColorContrastController;
use App\Http\Controllers\Tools\ColorConvertController;
use App\Http\Controllers\Tools\ColorGradientController;
use App\Http\Controllers\Tools\ColorPaletteController;
use App\Http\Controllers\Tools\ColorSchemeController;
use App\Http\Controllers\Tools\CssBorderRadiusController;
use App\Http\Controllers\Tools\CssBoxShadowController;
use App\Http\Controllers\Tools\CssFlexboxController;
use App\Http\Controllers\Tools\CssGlassmorphismController;
use App\Http\Controllers\Tools\CssGridController;
use App\Http\Controllers\Tools\FaviconGenerateController;
use App\Http\Controllers\Tools\GeneratorDummyDataController;
use App\Http\Controllers\Tools\HtmlFormatController;
use App\Http\Controllers\Tools\ImageColorExtractController;
use App\Http\Controllers\Tools\ImageCompressController;
use App\Http\Controllers\Tools\ImageConvertController;
use App\Http\Controllers\Tools\ImageCropController;
use App\Http\Controllers\Tools\ImageResizeController;
use App\Http\Controllers\Tools\ImageRotateController;
use App\Http\Controllers\Tools\JsonFormatController;
use App\Http\Controllers\Tools\OgpCheckController;
use App\Http\Controllers\Tools\OgpImageController;
use App\Http\Controllers\Tools\PatternCheckerboardController;
use App\Http\Controllers\Tools\PatternChevronController;
use App\Http\Controllers\Tools\PatternDotController;
use App\Http\Controllers\Tools\PatternGridController;
use App\Http\Controllers\Tools\PatternStripeController;
use App\Http\Controllers\Tools\PatternWaveController;
use App\Http\Controllers\Tools\SeoDescriptionCheckController;
use App\Http\Controllers\Tools\SeoTitleCheckController;
use App\Http\Controllers\Tools\StructuredDataCheckController;
use App\Http\Controllers\Tools\SvgOptimizeController;
use App\Http\Controllers\Tools\ToolDownloadController;
use App\Http\Controllers\Tools\UiPatternButtonController;
use App\Http\Controllers\Tools\UiPatternCardController;
use App\Http\Controllers\Tools\UiPatternFooterController;
use App\Http\Controllers\Tools\UiPatternFormCheckController;
use App\Http\Controllers\Tools\UiPatternFormGroupController;
use App\Http\Controllers\Tools\UiPatternFormInputController;
use App\Http\Controllers\Tools\UiPatternFormSelectController;
use App\Http\Controllers\Tools\UiPatternFormTextareaController;
use App\Http\Controllers\Tools\UiPatternHeaderController;
use App\Http\Controllers\Tools\UiPatternLoadingController;
use App\Http\Controllers\Tools\UiPatternModalController;
use App\Http\Controllers\Tools\UiPatternPaginationController;
use App\Http\Controllers\Tools\UiPatternSkeletonController;
use App\Http\Controllers\Tools\UiPatternToastController;
use App\Http\Controllers\Tools\UrlEncodeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/tool-download/{token}', [ToolDownloadController::class, 'show'])
    ->middleware('signed')
    ->name('tools.download');

Route::get('/image-compress', [ImageCompressController::class, 'show'])->name('image-compress.show');

Route::post('/image-compress', [ImageCompressController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-compress.process');

Route::get('/image-convert', [ImageConvertController::class, 'show'])->name('image-convert.show');

Route::post('/image-convert', [ImageConvertController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-convert.process');

Route::get('/image-resize', [ImageResizeController::class, 'show'])->name('image-resize.show');

Route::post('/image-resize', [ImageResizeController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-resize.process');

Route::get('/image-color-extract', [ImageColorExtractController::class, 'show'])->name('image-color-extract.show');

Route::post('/image-color-extract', [ImageColorExtractController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-color-extract.process');

Route::get('/image-rotate', [ImageRotateController::class, 'show'])->name('image-rotate.show');

Route::post('/image-rotate', [ImageRotateController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-rotate.process');

Route::get('/image-crop', [ImageCropController::class, 'show'])->name('image-crop.show');

Route::post('/image-crop', [ImageCropController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('image-crop.process');

Route::get('/color-convert', [ColorConvertController::class, 'show'])->name('color-convert.show');
Route::post('/color-convert/track', [ColorConvertController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-convert.track');

Route::get('/color-contrast', [ColorContrastController::class, 'show'])->name('color-contrast.show');
Route::post('/color-contrast/track', [ColorContrastController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-contrast.track');

Route::get('/color-palette', [ColorPaletteController::class, 'show'])->name('color-palette.show');
Route::post('/color-palette/track', [ColorPaletteController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-palette.track');

Route::get('/color-gradient', [ColorGradientController::class, 'show'])->name('color-gradient.show');
Route::post('/color-gradient/track', [ColorGradientController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-gradient.track');

Route::get('/color-scheme', [ColorSchemeController::class, 'show'])->name('color-scheme.show');
Route::post('/color-scheme/track', [ColorSchemeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('color-scheme.track');

Route::get('/ogp-image', [OgpImageController::class, 'show'])->name('ogp-image.show');

Route::post('/ogp-image', [OgpImageController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('ogp-image.process');

Route::get('/favicon-generate', [FaviconGenerateController::class, 'show'])->name('favicon-generate.show');

Route::post('/favicon-generate', [FaviconGenerateController::class, 'process'])
    ->middleware('throttle:image-tools')
    ->name('favicon-generate.process');

Route::get('/svg-optimize', [SvgOptimizeController::class, 'show'])->name('svg-optimize.show');
Route::post('/svg-optimize/track', [SvgOptimizeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('svg-optimize.track');

Route::get('/html-format', [HtmlFormatController::class, 'show'])->name('html-format.show');
Route::post('/html-format/track', [HtmlFormatController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('html-format.track');

Route::get('/json-format', [JsonFormatController::class, 'show'])->name('json-format.show');
Route::post('/json-format/track', [JsonFormatController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('json-format.track');

Route::get('/url-encode', [UrlEncodeController::class, 'show'])->name('url-encode.show');
Route::post('/url-encode/track', [UrlEncodeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('url-encode.track');

Route::get('/css-box-shadow', [CssBoxShadowController::class, 'show'])->name('css-box-shadow.show');
Route::post('/css-box-shadow/track', [CssBoxShadowController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-box-shadow.track');

Route::get('/css-border-radius', [CssBorderRadiusController::class, 'show'])->name('css-border-radius.show');
Route::post('/css-border-radius/track', [CssBorderRadiusController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-border-radius.track');

Route::get('/css-glassmorphism', [CssGlassmorphismController::class, 'show'])->name('css-glassmorphism.show');
Route::post('/css-glassmorphism/track', [CssGlassmorphismController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-glassmorphism.track');

Route::get('/css-flexbox', [CssFlexboxController::class, 'show'])->name('css-flexbox.show');
Route::post('/css-flexbox/track', [CssFlexboxController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-flexbox.track');

Route::get('/css-grid', [CssGridController::class, 'show'])->name('css-grid.show');
Route::post('/css-grid/track', [CssGridController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('css-grid.track');

Route::get('/base64-encode', [Base64EncodeController::class, 'show'])->name('base64-encode.show');
Route::post('/base64-encode/track', [Base64EncodeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('base64-encode.track');

Route::get('/seo-title-check', [SeoTitleCheckController::class, 'show'])->name('seo-title-check.show');
Route::post('/seo-title-check/track', [SeoTitleCheckController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('seo-title-check.track');

Route::get('/seo-description-check', [SeoDescriptionCheckController::class, 'show'])->name('seo-description-check.show');
Route::post('/seo-description-check/track', [SeoDescriptionCheckController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('seo-description-check.track');

Route::get('/ogp-check', [OgpCheckController::class, 'show'])->name('ogp-check.show');
Route::post('/ogp-check', [OgpCheckController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('ogp-check.check');

Route::get('/structured-data-check', [StructuredDataCheckController::class, 'show'])->name('structured-data-check.show');
Route::post('/structured-data-check', [StructuredDataCheckController::class, 'check'])
    ->middleware('throttle:10,1')
    ->name('structured-data-check.check');

Route::get('/pattern-dot', [PatternDotController::class, 'show'])->name('pattern-dot.show');
Route::post('/pattern-dot/track', [PatternDotController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-dot.track');

Route::get('/pattern-grid', [PatternGridController::class, 'show'])->name('pattern-grid.show');
Route::post('/pattern-grid/track', [PatternGridController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-grid.track');

Route::get('/pattern-stripe', [PatternStripeController::class, 'show'])->name('pattern-stripe.show');
Route::post('/pattern-stripe/track', [PatternStripeController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-stripe.track');

Route::get('/pattern-checkerboard', [PatternCheckerboardController::class, 'show'])->name('pattern-checkerboard.show');
Route::post('/pattern-checkerboard/track', [PatternCheckerboardController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-checkerboard.track');

Route::get('/pattern-chevron', [PatternChevronController::class, 'show'])->name('pattern-chevron.show');
Route::post('/pattern-chevron/track', [PatternChevronController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-chevron.track');

Route::get('/pattern-wave', [PatternWaveController::class, 'show'])->name('pattern-wave.show');
Route::post('/pattern-wave/track', [PatternWaveController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('pattern-wave.track');

Route::get('/generator-dummy-data', [GeneratorDummyDataController::class, 'show'])->name('generator-dummy-data.show');
Route::post('/generator-dummy-data/track', [GeneratorDummyDataController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('generator-dummy-data.track');

Route::get('/ui-pattern-button', [UiPatternButtonController::class, 'show'])->name('ui-pattern-button.show');
Route::post('/ui-pattern-button/track', [UiPatternButtonController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-button.track');

Route::get('/ui-pattern-card', [UiPatternCardController::class, 'show'])->name('ui-pattern-card.show');
Route::post('/ui-pattern-card/track', [UiPatternCardController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-card.track');

Route::get('/ui-pattern-pagination', [UiPatternPaginationController::class, 'show'])->name('ui-pattern-pagination.show');
Route::post('/ui-pattern-pagination/track', [UiPatternPaginationController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-pagination.track');

Route::get('/ui-pattern-modal', [UiPatternModalController::class, 'show'])->name('ui-pattern-modal.show');
Route::post('/ui-pattern-modal/track', [UiPatternModalController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-modal.track');

Route::get('/ui-pattern-toast', [UiPatternToastController::class, 'show'])->name('ui-pattern-toast.show');
Route::post('/ui-pattern-toast/track', [UiPatternToastController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-toast.track');

Route::get('/ui-pattern-loading', [UiPatternLoadingController::class, 'show'])->name('ui-pattern-loading.show');
Route::post('/ui-pattern-loading/track', [UiPatternLoadingController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-loading.track');

Route::get('/ui-pattern-skeleton', [UiPatternSkeletonController::class, 'show'])->name('ui-pattern-skeleton.show');
Route::post('/ui-pattern-skeleton/track', [UiPatternSkeletonController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-skeleton.track');

Route::get('/ui-pattern-form-input', [UiPatternFormInputController::class, 'show'])->name('ui-pattern-form-input.show');
Route::post('/ui-pattern-form-input/track', [UiPatternFormInputController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-form-input.track');

Route::get('/ui-pattern-form-textarea', [UiPatternFormTextareaController::class, 'show'])->name('ui-pattern-form-textarea.show');
Route::post('/ui-pattern-form-textarea/track', [UiPatternFormTextareaController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-form-textarea.track');

Route::get('/ui-pattern-form-check', [UiPatternFormCheckController::class, 'show'])->name('ui-pattern-form-check.show');
Route::post('/ui-pattern-form-check/track', [UiPatternFormCheckController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-form-check.track');

Route::get('/ui-pattern-form-select', [UiPatternFormSelectController::class, 'show'])->name('ui-pattern-form-select.show');
Route::post('/ui-pattern-form-select/track', [UiPatternFormSelectController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-form-select.track');

Route::get('/ui-pattern-form-group', [UiPatternFormGroupController::class, 'show'])->name('ui-pattern-form-group.show');
Route::post('/ui-pattern-form-group/track', [UiPatternFormGroupController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-form-group.track');

Route::get('/ui-pattern-header', [UiPatternHeaderController::class, 'show'])->name('ui-pattern-header.show');
Route::post('/ui-pattern-header/track', [UiPatternHeaderController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-header.track');

Route::get('/ui-pattern-footer', [UiPatternFooterController::class, 'show'])->name('ui-pattern-footer.show');
Route::post('/ui-pattern-footer/track', [UiPatternFooterController::class, 'track'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-footer.track');

Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');

Route::post('/premium/checkout', [PremiumController::class, 'checkout'])
    ->middleware('auth')
    ->name('premium.checkout');

Route::get('/premium/thanks', [PremiumController::class, 'thanks'])
    ->middleware('auth')
    ->name('premium.thanks');

Route::post('/premium/billing-portal', [PremiumController::class, 'billingPortal'])
    ->middleware('auth')
    ->name('premium.billing-portal');

Route::get('/mypage', [MyPageController::class, 'index'])
    ->middleware(['auth', 'premium'])
    ->name('mypage.index');

Route::get('/legal/tokushoho', [LegalController::class, 'tokushoho'])->name('legal.tokushoho');
Route::get('/legal/privacy-policy', [LegalController::class, 'privacyPolicy'])->name('legal.privacy-policy');
Route::get('/legal/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/legal/cookie-policy', [LegalController::class, 'cookiePolicy'])->name('legal.cookie-policy');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
