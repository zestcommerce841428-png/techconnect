document.addEventListener("click",t=>{if(!t.target.closest("[data-theme-toggle]"))return;const e=document.documentElement.classList.toggle("dark");localStorage.setItem("theme",e?"dark":"light")});
