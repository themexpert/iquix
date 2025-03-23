#Quix Launcher
Quix Launcher to install and validate quix


### Release Instructions

**Update `mainfest.xml`:**
1. Update the version number.
2. Update the download link.

**Update `iquix.php`:**
1. Update the version number.

**Create a ZIP Archive**:
1. Zip the entire directory, excluding `mainfest.xml`, `todo`, `.gitignore`, and `README.md`.


### **Upload ZIP Archive**:
```cmd
zip -r com_iquix_1.8.0.zip . -x "mainfest.xml" "todo" ".gitignore" "README.md"
```
