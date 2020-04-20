/**
 * Gulpfile setup
 * @link https://ahmadawais.com/my-advanced-gulp-workflow-for-wordpress-themes/
 */

// Project configuration
var project 		= 'storms-wc-calculost',   	 // Project name, used for build zip.
    theme_dir		= '../',					 // Theme base dir
	wp_content		= '../../../';				 // WordPress wp-content/ dir

// Load plugins
var gulp          = require('gulp'),
	pipeline 	  = require('readable-stream').pipeline,
	debug         = require('gulp-debug'),
	notify        = require('gulp-notify'),

	sourcemaps    = require('gulp-sourcemaps'),
	rename        = require('gulp-rename'),
	uglify        = require('gulp-uglify');

// Datestamp for cache busting
var getStamp = function() {
	var myDate = new Date();

	var myYear = myDate.getFullYear().toString();
	var myMonth = ('0' + (myDate.getMonth() + 1)).slice(-2);
	var myDay = ('0' + myDate.getDate()).slice(-2);
	var mySeconds = myDate.getSeconds().toString();

	return myYear + myMonth + myDay;
};

/**
 * Scripts
 * Look at /js/src files and concatenate those files, send them to /js where we then minimize the concatenated file.
 */
function scripts_source() {
	return pipeline(
		gulp.src( [
			'./js/src/**/*.js' // All our custom scripts
		] ),
		//debug(),
		gulp.dest( './js/' ),
		sourcemaps.init(),
		rename({ suffix: '.min' } ),
		uglify(),
		sourcemaps.write( './maps' ),
		gulp.dest( './js/' ),
		notify( { message: 'Source scripts task complete', onLast: true } )
	);
}
gulp.task('scripts', gulp.parallel(scripts_source));

// ==== TASKS ==== //

/**
 * Gulp Default Task
 * Compiles styles, fires-up browser sync, watches js and php files. Note browser sync task watches php files
 */

// Default Task
gulp.task('default', gulp.series(['scripts']));

// Watch Task
gulp.task('watch', gulp.series(['scripts'], function () {
	gulp.watch('./js/src/**/*.js', gulp.series('scripts'));
}));
