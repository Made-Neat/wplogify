import gulp from 'gulp';
import { deleteAsync } from 'del';
import gulpSass from 'gulp-sass';
import sass from 'sass';
import clean from 'gulp-clean';
import path from 'path';
import { fileURLToPath } from 'url';

// Emulate __dirname for ES Modules.
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Paths.
const scssPath = path.join(__dirname, 'wp-logify/assets/scss/**/*.scss');
const cssPath = path.join(__dirname, 'wp-logify/assets/css');
const pluginSourcePath = path.join(__dirname, 'wp-logify/**/*');
const pluginDestPath = path.join(__dirname, 'www/wp-content/plugins/wp-logify');

// Debug.
console.log('scssPath:', scssPath);
console.log('cssPath:', cssPath);
console.log('pluginSourcePath:', pluginSourcePath);
console.log('pluginDestPath:', pluginDestPath);

// Setup gulp-sass.
const sassProcessor = gulpSass(sass);

// Sass task.
gulp.task('sass', function () {
    return gulp.src(scssPath)
        .pipe(sassProcessor().on('error', sassProcessor.logError))
        .pipe(gulp.dest(cssPath));
});

// Clean plugin task.
gulp.task('clean-plugin', function () {
    return gulp.src(pluginDestPath, { read: false, allowEmpty: true })
        .pipe(clean());
});

// Copy plugin task.
gulp.task('copy-plugin', function () {
    return gulp.src(pluginSourcePath)
        .pipe(gulp.dest(pluginDestPath));
});

// Watch task.
gulp.task('watch', function () {
    gulp.watch(scssPath, gulp.series('sass'));
    gulp.watch(pluginSourcePath, gulp.series('clean-plugin', 'copy-plugin'));
});

gulp.task('default', gulp.series('sass', 'clean-plugin', 'copy-plugin', 'watch'));
