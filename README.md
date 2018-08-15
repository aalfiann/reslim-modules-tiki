### Detail module information

1. Namespace >> **modules/tiki**
2. Zip Archive source >> 
    https://github.com/aalfiann/reSlim-modules-tiki/archive/master.zip

### How to Integrate this module into reSlim?

1. Download zip then upload to reSlim server to the **modules/**
2. Extract zip then you will get new folder like **reSlim-modules-tiki-master**
3. Rename foldername **reSlim-modules-tiki-master** to **tiki**
4. Done

### How to Integrate this module into reSlim with Packager?

1. Make AJAX GET request to >>
    http://**{yourdomain.com}**/api/packager/install/zip/safely/**{yourusername}**/**{yourtoken}**/?lang=en&source=**{zip archive source}**&namespace=**{modul namespace}**

### Requirement
- This module is require **reSlim** minimum version **1.14.0**.
- This module is require [FlexibleConfig](https://github.com/aalfiann/reSlim-modules-flexibleconfig) module installed on reSlim.
- This module is using **Official API from Tiki Company** so this module is only works in localhost, if you want to get this online, you have to contact [Tiki Company](https://tiki.id) to whitelisting your ip address.