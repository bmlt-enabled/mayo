module.exports = {
    name: 'Version Management',
    description: 'Ensures version numbers are properly updated across all relevant files when making a release',
    
    rules: [
        {
            name: 'version-sync',
            description: 'Checks if version numbers are in sync across files when making a release',
            
            // Files that contain version numbers
            files: [
                'mayo-events-manager.php',
                'readme.txt',
                'package.json'
            ],
            
            // The check function
            check: async (context) => {
                const mainPlugin = await context.readFile('mayo-events-manager.php');
                const readmeTxt = await context.readFile('readme.txt');
                const packageJson = await context.readFile('package.json');
                
                // Extract versions
                const pluginVersion = mainPlugin.match(/Version:\s*(\d+\.\d+\.\d+)/)?.[1];
                const defineVersion = mainPlugin.match(/define\('MAYO_VERSION',\s*'(\d+\.\d+\.\d+)'\)/)?.[1];
                const readmeVersion = readmeTxt.match(/Stable tag:\s*(\d+\.\d+\.\d+)/)?.[1];
                const packageVersion = JSON.parse(packageJson).version;
                
                const errors = [];
                
                // Check if all versions match
                const versions = new Set([pluginVersion, defineVersion, readmeVersion, packageVersion]);
                if (versions.size > 1) {
                    errors.push('Version numbers are not consistent across files:');
                    if (pluginVersion !== defineVersion) {
                        errors.push('- Version in plugin header does not match MAYO_VERSION define');
                    }
                    if (pluginVersion !== readmeVersion) {
                        errors.push('- Version in plugin header does not match readme.txt stable tag');
                    }
                    if (pluginVersion !== packageVersion) {
                        errors.push('- Version in plugin header does not match package.json version');
                    }
                }
                
                return {
                    passed: errors.length === 0,
                    errors: errors
                };
            },
            
            fix: async (context) => {
                // This is a complex fix that requires human intervention
                return {
                    fixed: false,
                    message: 'Please manually update version numbers in:' +
                            '\n1. mayo-events-manager.php (both Version: and define)' +
                            '\n2. readme.txt (Stable tag:)' +
                            '\n3. package.json (version)'
                };
            }
        },
        
        {
            name: 'changelog-format',
            description: 'Ensures changelog entries in readme.txt follow the correct WordPress format',
            
            files: ['readme.txt'],
            
            check: async (context) => {
                const readmeTxt = await context.readFile('readme.txt');
                const errors = [];
                
                // Check format of each changelog entry
                const entries = readmeTxt.match(/=\s*\d+\.\d+\.\d+\s*=/g) || [];
                entries.forEach(entry => {
                    if (!entry.match(/=\s*\d+\.\d+\.\d+\s*=/)) {
                        errors.push(`Invalid changelog entry format: ${entry}`);
                    }
                });
                
                // Check bullet points format
                const bulletPoints = readmeTxt.match(/^\*\s+.*$/gm) || [];
                bulletPoints.forEach(point => {
                    if (!point.match(/^\*\s+[A-Z].*[\.\]]$/)) {
                        errors.push(`Invalid bullet point format: ${point}`);
                    }
                });
                
                return {
                    passed: errors.length === 0,
                    errors: errors
                };
            }
        }
    ]
}; 