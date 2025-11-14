"use client"

import { useState } from "react"
import Header from "@/components/header"
import SearchSection from "@/components/search-section"
import FeaturesSection from "@/components/features-section"
import FormatsSection from "@/components/formats-section"

export default function Home() {
  const [activeTab, setActiveTab] = useState("youtube-downloader")

  return (
    <main className="min-h-screen bg-background">
      <Header activeTab={activeTab} setActiveTab={setActiveTab} />
      <SearchSection />
      <FeaturesSection />
      <FormatsSection />
    </main>
  )
}
