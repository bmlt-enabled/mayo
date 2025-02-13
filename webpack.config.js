const path = require('path');

module.exports = {
    entry: {
        admin: './assets/js/src/admin.js',
        public: './assets/js/src/public.js'
    },
    output: {
        filename: '[name].bundle.js',
        path: path.resolve(__dirname, 'assets/js/dist')
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react']
                    }
                }
            }
        ]
    },
    externals: {
        'react': 'React',
        'react-dom': 'ReactDOM',
        '@wordpress/element': 'wp.element',
        '@wordpress/components': 'wp.components',
        '@wordpress/data': 'wp.data',
        '@wordpress/plugins': 'wp.plugins',
        '@wordpress/edit-post': 'wp.editPost',
        '@wordpress/i18n': 'wp.i18n'
    },
    resolve: {
        extensions: ['.js', '.jsx'],
    },
};
