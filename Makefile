# Define the name of the zip file
PLUGIN_NAME = mayo-events-manager
VERSION = 1.0.6
ZIP_FILE = $(PLUGIN_NAME)-$(VERSION).zip

FILES = assets includes plugin.php README.md templates vendor composer.json composer.lock

# Default target
all: $(ZIP_FILE)

# Create the zip file
$(ZIP_FILE): $(FILES)
	@echo "Creating zip file: $(ZIP_FILE)"
	find . -name ".DS_Store" -type f -delete
	find . -name ".DS_Store" -type f
	@zip --exclude .DS_Store --quiet --recurse-paths $(ZIP_FILE) $(FILES) 

# Clean up the zip file
clean:
	@echo "Cleaning up..."
	@rm -f $(ZIP_FILE)

# Phony targets
.PHONY: all clean