const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');

module.exports = {
    entry: {
        'admin-script': './src/js/admin-script.js',
        'admin-style': './src/scss/admin-style.scss'
    },
    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: 'js/[name].js',
        clean: false, // Don't clean assets folder as we have subfolders
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            },
            {
                test: /\.scss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    'sass-loader'
                ]
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].css'
        }),
        new BrowserSyncPlugin({
            // browse to http://localhost:3000/ during development,
            // ./public directory is being served
            host: 'localhost',
            port: 3000,
            proxy: 'http://localhost:8080', // Proxy local Docker WordPress
            files: [
                './**/*.php', // Watch PHP files
                './assets/js/*.js',
                './assets/css/*.css'
            ],
            reloadDelay: 200, // Wait a bit for files to save fully
        }, {
            // prevent BrowserSync from reloading the page
            // and let Webpack Dev Server take care of this
            // but here we want BrowserSync to handle it mostly for PHP
            reload: false
        })
    ]
};
