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
                'RELEASENOTES.md'
            ],
            
            // The check function
            check: async (context) => {
                const mainPlugin = await context.readFile('mayo-events-manager.php');
                const readmeTxt = await context.readFile('readme.txt');
                const releaseNotes = await context.readFile('RELEASENOTES.md');
                
                // Extract versions
                const pluginVersion = mainPlugin.match(/Version:\s*(\d+\.\d+\.\d+)/)?.[1];
                const defineVersion = mainPlugin.match(/define\('MAYO_VERSION',\s*'(\d+\.\d+\.\d+)'\)/)?.[1];
                const readmeVersion = readmeTxt.match(/Stable tag:\s*(\d+\.\d+\.\d+)/)?.[1];
                const releaseNotesVersion = releaseNotes.match(/###\s*(\d+\.\d+\.\d+)/)?.[1];
                
                const errors = [];
                
                // Check if all versions match
                const versions = new Set([pluginVersion, defineVersion, readmeVersion, releaseNotesVersion]);
                if (versions.size > 1) {
                    errors.push('Version numbers are not consistent across files:');
                    if (pluginVersion !== defineVersion) {
                        errors.push('- Version in plugin header does not match MAYO_VERSION define');
                    }
                    if (pluginVersion !== readmeVersion) {
                        errors.push('- Version in plugin header does not match readme.txt stable tag');
                    }
                    if (pluginVersion !== releaseNotesVersion) {
                        errors.push('- Version in plugin header does not match latest RELEASENOTES.md entry');
                    }
                }
                
                // Check if release notes entry has a date
                const latestReleaseEntry = releaseNotes.match(/###\s*\d+\.\d+\.\d+\s*\((.*?)\)/)?.[1];
                if (!latestReleaseEntry) {
                    errors.push('Latest release notes entry is missing a date');
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
                            '\n3. RELEASENOTES.md (add new version entry with date)'
                };
            }
        },
        
        {
            name: 'release-notes-format',
            description: 'Ensures release notes entries follow the correct format',
            
            files: ['RELEASENOTES.md'],
            
            check: async (context) => {
                const releaseNotes = await context.readFile('RELEASENOTES.md');
                const errors = [];
                
                // Check format of each release entry
                const entries = releaseNotes.match(/###\s*\d+\.\d+\.\d+\s*\(.*?\)/g) || [];
                entries.forEach(entry => {
                    if (!entry.match(/###\s*\d+\.\d+\.\d+\s*\([A-Z][a-z]+ \d{1,2}, \d{4}\)/)) {
                        errors.push(`Invalid release notes entry format: ${entry}`);
                    }
                });
                
                // Check bullet points format
                const bulletPoints = releaseNotes.match(/^\*\s+.*$/gm) || [];
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