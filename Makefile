# Define the name of the zip file
PLUGIN_NAME = mayo
VERSION = 1.0.1
ZIP_FILE = $(PLUGIN_NAME)-$(VERSION).zip

# Define the directories and files to include in the zip
FILES = assets includes plugin.php README.md templates vendor

# Default target
all: $(ZIP_FILE)

# Create the zip file
$(ZIP_FILE): $(FILES)
	@echo "Creating zip file: $(ZIP_FILE)"
	@zip -r $(ZIP_FILE) $(FILES)

# Clean up the zip file
clean:
	@echo "Cleaning up..."
	@rm -f $(ZIP_FILE)

# Phony targets
.PHONY: all clean