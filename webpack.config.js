const path = require('path');

module.exports = {
    entry: {
        admin: './assets/js/admin.js',
        public: './assets/js/public.js',
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/dist'),
        filename: '[name].bundle.js',
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-react'],
                    },
                },
            },
        ],
    },
    resolve: {
        extensions: ['.js', '.jsx'],
    },
};
